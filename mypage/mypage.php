<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../plan_limits.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: /index.php'); // ルートへリダイレクト
  exit();
}

$uploadSuccess = $_GET['upload_success'] ?? '0';

$userDir     = __DIR__ . '/../users/';
$profileFile = $userDir . $uid . '_profile.json';
$postsFile   = $userDir . $uid . '_posts.json';
$dictFile    = $userDir . $uid . '_dictionary.json';

$thoughtDict = file_exists($dictFile) ? json_decode(file_get_contents($dictFile), true) : [];
if (!is_array($thoughtDict)) $thoughtDict = [];

// Thought DNA profile
$profileDataFile = $userDir . $uid . '_thought_profile.json';
$thoughtProfile  = file_exists($profileDataFile) ? json_decode(file_get_contents($profileDataFile), true) : null;
$sessFile        = $userDir . $uid . '_quiz_sessions.json';
$quizSessions    = file_exists($sessFile) ? json_decode(file_get_contents($sessFile), true) : [];
$quizCount       = count($quizSessions ?? []);

$profile = ["display_name" => $userName, "title" => '', "bio" => '', "image" => '', "network_id" => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'] ?? $userName;
$networkId   = $profile['network_id'] ?? '';
$title = $profile['title'] ?? '';
$imagePath   = !empty($profile['image']) ? '../uploads/' . $uid . '/' . $profile['image'] : '../img/default-icon.png';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) {
  $posts = [];
  file_put_contents($postsFile, json_encode($posts));
}
$postsUpdated = false;
foreach ($posts as &$p) {
  if (empty($p['id'])) {
    $p['id'] = uniqid('post_');
    $postsUpdated = true;
  }
  if (!isset($p['status'])) {
    $p['status'] = '';
    $postsUpdated = true;
  }
}
unset($p);

if ($postsUpdated) {
  file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}



$visiblePosts = array_filter($posts, fn($p) => ($p['status'] ?? '') !== '削除済');
$postLimit = $plan_limits[$userPlan]['post_limit'] ?? 100;
$remainingPosts = max(0, $postLimit - count($visiblePosts));

// Fetch Liked posts
$likedPosts = [];
$userFiles = glob($userDir . '*_posts.json');
foreach ($userFiles as $file) {
    $filename = basename($file);
    $authorUid = str_replace('_posts.json', '', $filename);
    
    $userPosts = json_decode(file_get_contents($file), true) ?: [];
    foreach ($userPosts as $idx => $p) {
        if (($p['status'] ?? '') !== '削除済' && !empty($p['likes']) && in_array($uid, $p['likes'])) {
            $p['author_uid'] = $authorUid;
            $p['original_index'] = $idx;
            $likedPosts[] = $p;
        }
    }
}
usort($likedPosts, function($a, $b) {
    return strcmp($b['id'] ?? '', $a['id'] ?? '');
});

// Fetch Saved posts
$savedFile = $userDir . $uid . '_saved.json';
$savedItems = file_exists($savedFile) ? (json_decode(file_get_contents($savedFile), true) ?: []) : [];
$savedPosts = [];
foreach ($savedItems as $sItem) {
    $authorUid = $sItem['author_uid'] ?? '';
    $sPostId = $sItem['post_id'] ?? '';
    if ($authorUid && $sPostId) {
        $authorPostsFile = $userDir . $authorUid . '_posts.json';
        if (file_exists($authorPostsFile)) {
            $authorPosts = json_decode(file_get_contents($authorPostsFile), true) ?: [];
            foreach ($authorPosts as $idx => $p) {
                if (($p['id'] ?? '') === $sPostId && ($p['status'] ?? '') !== '削除済') {
                    $p['author_uid'] = $authorUid;
                    $p['original_index'] = $idx;
                    $savedPosts[] = $p;
                    break;
                }
            }
        }
    }
}

// Card Renderer Helper
function renderPostCard($post, $authorUid, $uid, $userDir, $isOwnPostsTab = false) {
    $profFile = $userDir . $authorUid . '_profile.json';
    $prof = ["display_name" => $authorUid, "image" => '', "network_id" => ''];
    if (file_exists($profFile)) {
        $prof = array_merge($prof, json_decode(file_get_contents($profFile), true) ?: []);
    }
    $displayName = $prof['display_name'] ?? $authorUid;
    $networkId   = $prof['network_id'] ?? '';
    $imagePath   = !empty($prof['image']) ? '../uploads/' . $authorUid . '/' . $prof['image'] : '../img/default-icon.png';

    $postId      = $post['id'] ?? '';
    $postStatus  = $post['status'] ?? '';
    $postTitle   = $post['title'] ?? '';
    $postDate    = $post['date'] ?? '';
    $postSummary = $post['summary'] ?? '';
    $postText    = $post['text'] ?? '';
    $af          = $post['audio_file'] ?? $post['audio'] ?? '';
    $audioSrc    = !empty($af) ? '../audio_proxy.php?target_uid=' . urlencode($authorUid) . '&path=' . urlencode($af) : '';
    $likeCount   = isset($post['likes']) ? count($post['likes']) : 0;
    $commentCount = $post['comment_count'] ?? 0;
    $isLiked     = !empty($post['likes']) && in_array($uid, $post['likes']);
    $isShared    = !empty($post['is_shared']);
    $isAnalyzing = ($postStatus === '解析中');
    $isError     = ($postStatus === 'エラー' || (empty($postSummary) && empty($postText) && !empty($af) && !$isAnalyzing));
    
    // Check saved status
    $savedFile = $userDir . $uid . '_saved.json';
    $savedItems = file_exists($savedFile) ? (json_decode(file_get_contents($savedFile), true) ?: []) : [];
    $isSaved = false;
    foreach ($savedItems as $sItem) {
        if (($sItem['post_id'] ?? '') === $postId && ($sItem['author_uid'] ?? '') === $authorUid) {
            $isSaved = true;
            break;
        }
    }

    $displayCaption = $postSummary ?: mb_strimwidth(strip_tags($postText), 0, 120, '…');
    $hasFullText = !empty($postText) && mb_strlen(strip_tags($postText)) > 50;

    // Find original index
    $originalIndex = $post['original_index'] ?? -1;
    if ($originalIndex === -1) {
        $postsFile = $userDir . $authorUid . '_posts.json';
        if (file_exists($postsFile)) {
            $postsData = json_decode(file_get_contents($postsFile), true) ?: [];
            foreach ($postsData as $k => $p) {
                if (($p['id'] ?? '') === $postId) {
                    $originalIndex = $k;
                    break;
                }
            }
        }
    }
    ?>
    <article class="post-card" id="post-card-<?= htmlspecialchars($postId) ?>" data-id="<?= htmlspecialchars($postId) ?>">
      
      <div class="post-header">
        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="post-avatar">
        <div class="post-header__info">
          <div class="post-username"><?= htmlspecialchars($displayName) ?><?= !empty($networkId) ? ' <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400;">@'.htmlspecialchars($networkId).'</span>' : '' ?></div>
          <div class="post-date"><?= htmlspecialchars($postDate) ?></div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
          <?php if ($isOwnPostsTab): ?>
          <span class="sort-actions">
            <button onclick="movePost(this, 'up')" class="sort-btn" title="上に移動">
              <i class="fas fa-chevron-up"></i>
            </button>
            <button onclick="movePost(this, 'down')" class="sort-btn" title="下に移動">
              <i class="fas fa-chevron-down"></i>
            </button>
          </span>
          <?php endif; ?>

          <?php if ($authorUid === $uid): ?>
          <div class="relative">
            <button class="post-menu-btn" onclick="toggleMenu('<?= htmlspecialchars($postId) ?>')" aria-label="メニュー">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="owner-menu" id="menu-<?= htmlspecialchars($postId) ?>" style="display:none;">
              <a href="edit_post.php?id=<?= htmlspecialchars($postId) ?>" class="owner-menu__item">
                <i class="fas fa-edit"></i> 編集
              </a>
              <?php if (!empty($af)): ?>
              <button class="owner-menu__item" onclick="doAction('<?= htmlspecialchars($postId) ?>', 'retry'); toggleMenu('<?= htmlspecialchars($postId) ?>')">
                <i class="fas fa-redo"></i> 再解析
              </button>
              <?php endif; ?>
              <button class="owner-menu__item" onclick="sharePost('<?= htmlspecialchars($postId) ?>', <?= $isShared ? 'true' : 'false' ?>); toggleMenu('<?= htmlspecialchars($postId) ?>')">
                <i class="fas fa-share-alt"></i> <?= $isShared ? '共有を解除' : 'タイムラインに共有' ?>
              </button>
              <button class="owner-menu__item owner-menu__item--danger" onclick="doAction('<?= htmlspecialchars($postId) ?>', 'delete'); toggleMenu('<?= htmlspecialchars($postId) ?>')">
                <i class="fas fa-trash"></i> 削除
              </button>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($isAnalyzing): ?>
      <div class="post-status-badge badge-analyzing">
        <i class="fas fa-spinner fa-spin"></i> 解析中...
      </div>
      <?php elseif ($isError): ?>
      <div class="post-status-badge badge-error" onclick="doAction('<?= htmlspecialchars($postId) ?>', 'retry')" title="タップして再解析">
        <i class="fas fa-exclamation-triangle"></i> 解析未完了・タップして再試行
      </div>
      <?php endif; ?>

      <?php if (!empty($audioSrc)): ?>
      <div class="post-audio" id="player-wrap-<?= htmlspecialchars($postId) ?>">
        <button class="audio-play-btn" onclick="toggleAudio('<?= htmlspecialchars($postId) ?>')" id="play-btn-<?= htmlspecialchars($postId) ?>" aria-label="再生">
          <i class="fas fa-play" id="play-icon-<?= htmlspecialchars($postId) ?>"></i>
        </button>
        <div class="audio-progress-wrapper">
          <input type="range" class="audio-progress" id="progress-<?= htmlspecialchars($postId) ?>" value="0" min="0" max="100" step="0.1"
                 oninput="seekAudio('<?= htmlspecialchars($postId) ?>', this.value)">
          <span class="audio-time" id="time-<?= htmlspecialchars($postId) ?>">0:00</span>
        </div>
        <audio id="audio-<?= htmlspecialchars($postId) ?>"
               src="<?= htmlspecialchars($audioSrc) ?>"
               preload="metadata"
               onloadedmetadata="initDuration('<?= htmlspecialchars($postId) ?>')"
               ontimeupdate="updateProgress('<?= htmlspecialchars($postId) ?>')"
               onended="audioEnded('<?= htmlspecialchars($postId) ?>')">
        </audio>
      </div>
      <?php endif; ?>

      <?php if (!empty($displayCaption) && !$isAnalyzing): ?>
      <div class="post-caption collapsed" id="caption-<?= htmlspecialchars($postId) ?>">
        <strong><?= htmlspecialchars($postTitle) ?></strong>
        <?php if (!empty($post['original_title'])): ?>
          <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            <i class="fas fa-file-audio"></i> 元ファイル名: <?= htmlspecialchars($post['original_title']) ?>
          </div>
        <?php endif; ?>
        <br>
        <?= nl2br(htmlspecialchars($displayCaption)) ?>
      </div>

      <?php if ($hasFullText): ?>
      <button class="post-read-more" id="readmore-<?= htmlspecialchars($postId) ?>"
              onclick="expandPost('<?= htmlspecialchars($postId) ?>')">
        続きを読む
      </button>
      <?php endif; ?>

      <?php if ($hasFullText): ?>
      <div class="post-full-text" id="fulltext-<?= htmlspecialchars($postId) ?>" style="display:none;">
        <div class="post-full-text-content"><?= htmlspecialchars(strip_tags($postText)) ?></div>
        <div style="margin-top:12px;">
          <a href="view_post.php?uid=<?= urlencode($authorUid) ?>&index=<?= $originalIndex ?>" class="btn btn-ghost btn-sm" style="padding-left:0;">
            <i class="fas fa-external-link-alt"></i> 詳細ページを開く
          </a>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="post-actions">
        <button class="action-btn <?= $isLiked ? 'liked' : '' ?>" onclick="toggleLike('<?= htmlspecialchars($authorUid) ?>', <?= $originalIndex ?>, '<?= htmlspecialchars($postId) ?>', this)" id="like-btn-<?= htmlspecialchars($postId) ?>">
          <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart" id="like-icon-<?= htmlspecialchars($postId) ?>"></i>
          <span id="like-count-<?= htmlspecialchars($postId) ?>"><?= $likeCount > 0 ? $likeCount : '' ?></span>
        </button>
        <a href="view_post.php?uid=<?= urlencode($authorUid) ?>&index=<?= $originalIndex ?>#comments" class="action-btn">
          <i class="far fa-comment"></i>
          <span><?= $commentCount > 0 ? $commentCount : '' ?></span>
        </a>
        <button class="action-btn <?= $isSaved ? 'saved' : '' ?>" onclick="toggleSave('<?= htmlspecialchars($authorUid) ?>', '<?= htmlspecialchars($postId) ?>', this)" id="save-btn-<?= htmlspecialchars($postId) ?>">
          <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-bookmark" id="save-icon-<?= htmlspecialchars($postId) ?>"></i>
        </button>
        <a href="view_post.php?uid=<?= urlencode($authorUid) ?>&index=<?= $originalIndex ?>" class="action-btn action-btn--edit" title="詳細">
          <i class="fas fa-chevron-right"></i>
        </a>
      </div>

    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Udatsu</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
<body>

<!-- ============================================================
     APP HEADER
     ============================================================ -->
<header class="app-header">
  <a href="mypage.php" class="app-header__logo" style="text-decoration: none; display: flex; align-items: center;">
    <img src="../img/udatsu-logo.png" alt="Udatsu" style="height: 32px; filter: drop-shadow(0 0 5px rgba(252,200,0,0.5));">
  </a>
  <div class="app-header__actions">
    <!-- AI Chat Bot Link -->
    <a href="bot.php" class="icon-btn" aria-label="AIチャット" title="思考の分身 AIチャット">
      <i class="fas fa-brain" style="color: var(--brand); text-shadow: 0 0 8px var(--brand-glow);"></i>
    </a>
    <!-- Notification -->
    <a href="#" class="icon-btn" aria-label="通知">
      <i class="fas fa-bell"></i>
    </a>
  </div>
</header>

<!-- ============================================================
     MAIN FEED
     ============================================================ -->
<main class="app-main">

  <!-- Toast (upload success) -->
  <?php if ($uploadSuccess === '1'): ?>
  <div class="toast success show" id="uploadToast">
    <i class="fas fa-check-circle"></i> 音声を受け付けました。解析中です。
  </div>
  <script>setTimeout(()=>{ const t=document.getElementById('uploadToast'); if(t){t.classList.remove('show');} }, 4000);</script>
  <?php endif; ?>

  <!-- Feed -->
  <div class="feed" id="feed">
    
    <!-- AI Chat Bot Banner -->
    <div class="ai-chat-banner" style="margin: 0 16px 16px; background: linear-gradient(135deg, rgba(252, 200, 0, 0.1) 0%, rgba(252, 200, 0, 0.02) 100%); border: 1px solid var(--border-brand); border-radius: var(--radius-card); padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: var(--shadow-card);">
      <div style="flex: 1;">
        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--brand); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-brain"></i> 思考の分身 AIチャット
        </h4>
        <p style="font-size: 0.78rem; color: var(--text-secondary); line-height: 1.4; margin: 0;">
          あなたのボイスジャーナリング（<?= count($visiblePosts) ?>件）を学習したAIの分身と対話します。
        </p>
      </div>
      <a href="bot.php" class="btn btn-primary btn-sm" style="flex-shrink: 0; padding: 6px 16px; border-radius: var(--radius-pill); font-size: 0.78rem; font-weight: 700; text-decoration: none;">
        話しかける
      </a>
    </div>

    <div class="profile-tabs">
      <button class="profile-tab active" id="tab-btn-posts" onclick="switchProfileTab('posts')">ジャーナル</button>
      <button class="profile-tab" id="tab-btn-likes" onclick="switchProfileTab('likes')">いいね</button>
      <button class="profile-tab" id="tab-btn-saved" onclick="switchProfileTab('saved')">保存</button>
    </div>

    <!-- Tab 1: Posts (Journal) -->
    <div id="tab-content-posts" class="tab-pane">
      <?php if (count($visiblePosts) === 0): ?>
      <div class="empty-state">
        <i class="fas fa-microphone-slash"></i>
        <h3>まだ投稿がありません</h3>
        <p>LINEで音声を送るか、マイクボタンから録音してみましょう。</p>
        <a href="../voyager_upload.php" class="btn btn-primary btn-sm">
          <i class="fas fa-microphone"></i> 録音する
        </a>
      </div>
      <?php else: ?>
        <?php foreach ($visiblePosts as $post): ?>
          <?php renderPostCard($post, $uid, $uid, $userDir, true); ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Tab 2: Likes -->
    <div id="tab-content-likes" class="tab-pane" style="display:none;">
      <?php if (count($likedPosts) === 0): ?>
      <div class="empty-state">
        <i class="far fa-heart" style="font-size: 2rem; margin-bottom: 10px;"></i>
        <h3>いいねした投稿はありません</h3>
      </div>
      <?php else: ?>
        <?php foreach ($likedPosts as $post): ?>
          <?php renderPostCard($post, $post['author_uid'] ?? '', $uid, $userDir, false); ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Tab 3: Saved -->
    <div id="tab-content-saved" class="tab-pane" style="display:none;">
      <?php if (count($savedPosts) === 0): ?>
      <div class="empty-state">
        <i class="far fa-bookmark" style="font-size: 2rem; margin-bottom: 10px;"></i>
        <h3>保存した投稿はありません</h3>
      </div>
      <?php else: ?>
        <?php foreach ($savedPosts as $post): ?>
          <?php renderPostCard($post, $post['author_uid'] ?? '', $uid, $userDir, false); ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /feed -->
</main>

<!-- ============================================================
     BOTTOM NAV
     ============================================================ -->
<nav class="app-nav">
  <a href="mypage.php" class="nav-item active">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="timeline.php" class="nav-item">
    <i class="fas fa-compass"></i>
    <span>タイムライン</span>
  </a>
  <a href="../voyager_upload.php" class="nav-item" aria-label="録音">
    <div class="nav-record">
      <i class="fas fa-microphone"></i>
    </div>
  </a>
  <a href="network.php" class="nav-item">
    <i class="fas fa-user-friends"></i>
    <span>つながり</span>
  </a>
  <a href="profile.php" class="nav-item">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>

<!-- Toast (global) -->
<div class="toast" id="globalToast"></div>

<script>
/* =============================================================
   Theme setting (load-only)
   ============================================================= */
(function() {
  const saved = localStorage.getItem('udatsu_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
})();

/* =============================================================
   Tab Switcher
   ============================================================= */
function switchProfileTab(tabName) {
  // Update active tab button style
  document.querySelectorAll('.profile-tab').forEach(btn => btn.classList.remove('active'));
  const activeBtn = document.getElementById('tab-btn-' + tabName);
  if (activeBtn) activeBtn.classList.add('active');

  // Show selected content and hide others
  document.querySelectorAll('.tab-pane').forEach(pane => pane.style.display = 'none');
  const activePane = document.getElementById('tab-content-' + tabName);
  if (activePane) activePane.style.display = 'block';
  
  // Pause any currently playing audio on tab switch
  if (currentAudioId) {
    const audio = document.getElementById('audio-' + currentAudioId);
    const icon = document.getElementById('play-icon-' + currentAudioId);
    if (audio) audio.pause();
    if (icon) icon.className = 'fas fa-play';
    currentAudioId = null;
  }
}

/* =============================================================
   Toast helper
   ============================================================= */
function showToast(msg, type='') {
  const t = document.getElementById('globalToast');
  t.textContent = msg;
  t.className = 'toast ' + type + ' show';
  setTimeout(() => { t.classList.remove('show'); }, 3000);
}

/* =============================================================
   Owner menu
   ============================================================= */
function toggleMenu(postId) {
  const menu = document.getElementById('menu-' + postId);
  if (!menu) return;
  const isOpen = menu.style.display !== 'none';
  document.querySelectorAll('.owner-menu').forEach(m => m.style.display = 'none');
  if (!isOpen) menu.style.display = 'block';
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.post-menu-btn') && !e.target.closest('.owner-menu')) {
    document.querySelectorAll('.owner-menu').forEach(m => m.style.display = 'none');
  }
});

/* =============================================================
   Audio player
   ============================================================= */
let currentAudioId = null;

function initDuration(postId) {
  const audio = document.getElementById('audio-' + postId);
  const timeEl = document.getElementById('time-' + postId);
  if (!audio || !timeEl) return;
  const dur = (audio.duration && !isNaN(audio.duration)) ? formatTime(audio.duration) : '--:--';
  timeEl.textContent = '0:00 / ' + dur;
}

function toggleAudio(postId) {
  const audio = document.getElementById('audio-' + postId);
  const icon  = document.getElementById('play-icon-' + postId);
  if (!audio) return;

  if (currentAudioId && currentAudioId !== postId) {
    const prev = document.getElementById('audio-' + currentAudioId);
    const prevIcon = document.getElementById('play-icon-' + currentAudioId);
    if (prev) prev.pause();
    if (prevIcon) prevIcon.className = 'fas fa-play';
    currentAudioId = null;
  }

  if (audio.paused) {
    audio.play().catch(e => console.warn('Playback error:', e));
    icon.className = 'fas fa-pause';
    currentAudioId = postId;
  } else {
    audio.pause();
    icon.className = 'fas fa-play';
    currentAudioId = null;
  }
}

function updateProgress(postId) {
  const audio    = document.getElementById('audio-' + postId);
  const progress = document.getElementById('progress-' + postId);
  const timeEl   = document.getElementById('time-' + postId);
  if (!audio || !progress || !timeEl) return;

  const pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
  progress.value = pct;

  const cur = formatTime(audio.currentTime);
  const dur = (audio.duration && !isNaN(audio.duration)) ? formatTime(audio.duration) : '--:--';
  timeEl.textContent = cur + ' / ' + dur;
}

function seekAudio(postId, value) {
  const audio = document.getElementById('audio-' + postId);
  if (!audio || !audio.duration) return;
  audio.currentTime = (value / 100) * audio.duration;
}

function audioEnded(postId) {
  const icon = document.getElementById('play-icon-' + postId);
  const progress = document.getElementById('progress-' + postId);
  if (icon) icon.className = 'fas fa-play';
  if (progress) progress.value = 0;
  currentAudioId = null;
}

function formatTime(sec) {
  if (isNaN(sec)) return '0:00';
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60).toString().padStart(2, '0');
  return m + ':' + s;
}

/* =============================================================
   Read more / expand & Markdown Parse
   ============================================================= */
function expandPost(postId) {
  const caption  = document.getElementById('caption-' + postId);
  const fulltext = document.getElementById('fulltext-' + postId);
  const btn      = document.getElementById('readmore-' + postId);
  if (!fulltext) return;

  const isOpen = fulltext.style.display !== 'none';
  if (isOpen) {
    fulltext.style.display = 'none';
    if (caption) caption.classList.add('collapsed');
    if (btn) btn.textContent = '続きを読む';
  } else {
    // Parse markdown on first expand
    const contentEl = fulltext.querySelector('.post-full-text-content');
    if (contentEl && !contentEl.dataset.parsed) {
      const rawText = contentEl.textContent;
      contentEl.innerHTML = marked.parse(rawText);
      contentEl.dataset.parsed = 'true';
    }
    
    fulltext.style.display = 'block';
    if (caption) caption.classList.remove('collapsed');
    if (btn) btn.textContent = '閉じる';
  }
}

/* =============================================================
   Like / Save (optimistic UI)
   ============================================================= */
function toggleLike(authorUid, postIndex, postId, btn) {
  const icon = btn.querySelector('i');
  const count = btn.querySelector('.like-count') || document.getElementById('like-count-' + postId);
  if (!btn) return;

  const liked = btn.classList.toggle('liked');
  icon.className = liked ? 'fas fa-heart' : 'far fa-heart';
  if (count) {
    const cur = parseInt(count.textContent) || 0;
    count.textContent = liked ? (cur + 1) || '1' : (cur - 1) > 0 ? (cur - 1) : '';
  }

  const fd = new FormData();
  fd.append('action', liked ? 'like' : 'unlike');
  fd.append('target_uid', authorUid);
  fd.append('post_index', postIndex);
  fd.append('id', postId);
  fetch('submit_interaction.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .catch(() => {});
}

function toggleSave(authorUid, postId, btn) {
  const icon = btn.querySelector('i');
  if (!btn) return;
  const saved = btn.classList.toggle('saved');
  icon.className = saved ? 'fas fa-bookmark' : 'far fa-bookmark';
  showToast(saved ? '保存しました' : '保存を解除しました', saved ? 'success' : '');

  const fd = new FormData();
  fd.append('action', saved ? 'save' : 'unsave');
  fd.append('target_uid', authorUid);
  fd.append('id', postId);
  fetch('submit_interaction.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .catch(() => {});
}

/* =============================================================
   Sort actions (up / down)
   ============================================================= */
function movePost(btn, direction) {
  const card = btn.closest('.post-card');
  if (!card) return;
  
  const parent = card.parentNode;
  let target = null;
  if (direction === 'up') {
    target = card.previousElementSibling;
  } else if (direction === 'down') {
    target = card.nextElementSibling;
  }
  
  if (!target || !target.classList.contains('post-card')) return;
  
  const cards = Array.from(parent.querySelectorAll('.post-card'));
  const firstPositions = cards.map(c => ({
    el: c,
    rect: c.getBoundingClientRect()
  }));
  
  card.classList.add('moving-active');
  
  if (direction === 'up') {
    parent.insertBefore(card, target);
  } else {
    parent.insertBefore(target, card);
  }
  
  const lastPositions = firstPositions.map(pos => ({
    el: pos.el,
    firstRect: pos.rect,
    lastRect: pos.el.getBoundingClientRect()
  }));
  
  lastPositions.forEach(pos => {
    const deltaY = pos.firstRect.top - pos.lastRect.top;
    if (deltaY !== 0) {
      pos.el.style.transition = 'none';
      pos.el.style.transform = `translateY(${deltaY}px)`;
    }
  });
  
  document.body.offsetHeight;
  
  lastPositions.forEach(pos => {
    const deltaY = pos.firstRect.top - pos.lastRect.top;
    if (deltaY !== 0) {
      pos.el.style.transition = 'transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.4s, box-shadow 0.4s, background-color 0.4s';
      pos.el.style.transform = '';
    }
  });
  
  saveNewOrder();
  
  setTimeout(() => {
    card.classList.remove('moving-active');
    lastPositions.forEach(pos => {
      pos.el.style.transition = '';
      pos.el.style.transform = '';
    });
  }, 400);
}

function saveNewOrder() {
  const postIds = Array.from(document.getElementById('tab-content-posts').querySelectorAll('.post-card')).map(card => card.getAttribute('data-id'));
  
  const formData = new FormData();
  formData.append('order', JSON.stringify(postIds));
  
  fetch('save_order_ajax.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'ok') {
      console.log('Order saved successfully');
    } else {
      console.error('Failed to save order:', data.message);
    }
  })
  .catch(err => {
    console.error('Error saving order:', err);
  });
}

/* =============================================================
   Post actions (delete / retry / share)
   ============================================================= */
function doAction(postId, action) {
  if (action === 'delete' && !confirm('この投稿を削除しますか？')) return;

  const fd = new FormData();
  fd.append('id', postId);
  fd.append('action', action);
  const endpoint = action === 'retry' ? 'retry_analysis.php' : 'submit_post.php';

  fetch(endpoint, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok' || data.status === 'success' || data.status === 'async_started') {
        if (action === 'delete') {
          const card = document.getElementById('post-card-' + postId);
          if (card) { card.style.opacity = '0'; card.style.transition = 'opacity 0.3s'; setTimeout(() => card.remove(), 300); }
          showToast('削除しました');
        } else if (action === 'retry') {
          showToast('再解析を開始しました', 'success');
          startPolling();
        }
      } else {
        showToast(data.message || 'エラーが発生しました', 'error');
      }
    })
    .catch(() => showToast('通信エラーが発生しました', 'error'));
}

function sharePost(postId, isCurrentlyShared) {
  const action = isCurrentlyShared ? 'unshare' : 'share';
  if (action === 'share' && !confirm('タイムラインにこの投稿を共有しますか？')) return;
  doAction(postId, action);
}

/* =============================================================
   Auto-polling for analyzing posts
   ============================================================= */
let pollInterval = null;
function startPolling() {
  if (pollInterval) return;
  pollInterval = setInterval(function() {
    fetch('poll_status.php')
      .then(r => r.json())
      .then(d => { if (!d.has_processing) { clearInterval(pollInterval); location.reload(); } })
      .catch(() => {});
  }, 5000);
}

const hasProcessing = <?php echo (count(array_filter($posts ?? [], fn($p) => ($p['status'] ?? '') === '解析中')) > 0) ? 'true' : 'false'; ?>;
if (hasProcessing) startPolling();
</script>

</body>
</html>

