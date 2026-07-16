<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../plan_limits.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: index.php');
  exit();
}

$targetUid = $_GET['uid'] ?? $uid;
$isOwnProfile = ($targetUid === $uid);

$userDir     = __DIR__ . '/../users/';
$profileFile = $userDir . $targetUid . '_profile.json';
$postsFile   = $userDir . $targetUid . '_posts.json';

$profile = ["display_name" => ($isOwnProfile ? $userName : 'Unknown User'), "title" => '', "bio" => '', "image" => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'];
$title = $profile['title'] ?? '';
$imagePath   = !empty($profile['image']) ? '../uploads/' . $targetUid . '/' . $profile['image'] : '../img/default-icon.png';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
foreach ($posts as &$p) {
  if (!isset($p['status'])) {
    $p['status'] = '';
  }
}
unset($p);

function getPostStatusInfo($status) {
  if ($status === 'My Udastack追加済') {
    return ['label' => 'My Udastack追加済', 'class' => 'stacked'];
  }
  return ['label' => '', 'class' => ''];
}

$visiblePosts = array_filter($posts, function($p) use ($isOwnProfile) {
    if ($p['status'] === '削除済') return false;
    if (!$isOwnProfile && empty($p['is_shared'])) return false;
    return true;
});
$postLimit = $plan_limits[$userPlan]['post_limit'] ?? 100;
$remainingPosts = max(0, $postLimit - count($visiblePosts));


?>
<!DOCTYPE html>
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Udatsu | プロフィール</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <style>
    .profile-header {
      padding: 30px 20px;
      text-align: center;
      background: var(--bg-secondary);
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }
    .profile-header img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      border: 3px solid var(--accent-color);
    }
    .profile-name {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 5px;
    }
    .profile-title {
      font-size: 0.9rem;
      color: var(--accent-color);
      margin-bottom: 15px;
    }
    .profile-bio {
      font-size: 0.95rem;
      color: var(--text-secondary);
      line-height: 1.5;
      margin-bottom: 20px;
      padding: 0 20px;
    }
    .profile-stats {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-bottom: 20px;
    }
    .stat-item {
      text-align: center;
    }
    .stat-val {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--text-primary);
    }
    .stat-lbl {
      font-size: 0.8rem;
      color: var(--text-tertiary);
    }
    .action-row {
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .btn-edit {
      padding: 8px 24px;
      border-radius: 20px;
      background: var(--bg-tertiary);
      color: var(--text-primary);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid var(--border-color);
    }
    .btn-edit:active {
      background: var(--bg-secondary);
    }
    .btn-logout {
      padding: 8px 24px;
      border-radius: 20px;
      background: rgba(255, 68, 68, 0.1);
      color: #ff4444;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid rgba(255, 68, 68, 0.2);
    }
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-tertiary);
    }
  </style>
</head>
<body>

<header class="app-header">
  <a href="mypage.php" class="app-header__logo" style="text-decoration: none; display: flex; align-items: center;">
    <img src="../img/udatsu-logo.png" alt="Udatsu" style="height: 32px; filter: drop-shadow(0 0 5px rgba(252,200,0,0.5));">
  </a>
  <div class="app-header__actions">
    <!-- AI Chat Bot Link -->
    <a href="bot.php" class="icon-btn" aria-label="AIチャット" title="思考の分身 AIチャット">
      <i class="fas fa-brain" style="color: var(--brand); text-shadow: 0 0 8px var(--brand-glow);"></i>
    </a>
  </div>
</header>

<main class="app-main" style="padding-top: 60px;">
  
  <div class="profile-header">
    <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>">
    <div class="profile-name"><?= htmlspecialchars($displayName) ?></div>
    <div class="profile-title"><?= htmlspecialchars($title) ?></div>
    
    <div class="profile-stats">
      <div class="stat-item">
        <div class="stat-val"><?= count($visiblePosts) ?></div>
        <div class="stat-lbl">投稿</div>
      </div>
      <div class="stat-item">
        <div class="stat-val"><?= $remainingPosts ?></div>
        <div class="stat-lbl">残り枠</div>
      </div>
      <div class="stat-item">
        <div class="stat-val"><?= htmlspecialchars($userPlan) ?></div>
        <div class="stat-lbl">プラン</div>
      </div>
    </div>

    <?php if (!empty($profile['bio'])): ?>
    <div class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></div>
    <?php endif; ?>

    <div class="action-row">
      <?php if ($isOwnProfile): ?>
        <a href="edit_profile.php" class="btn btn-primary">プロフィールを編集</a>
        <a href="logout.php" class="btn-logout">ログアウト</a>
      <?php else: ?>
        <?php
          $myFollowing = file_exists($userDir . $uid . '_following.json') ? json_decode(file_get_contents($userDir . $uid . '_following.json'), true) : [];
          $isFollowing = in_array($targetUid, is_array($myFollowing) ? $myFollowing : []);
        ?>
        <button onclick="toggleFollow('<?= htmlspecialchars($targetUid) ?>', <?= $isFollowing ? 'true' : 'false' ?>)" class="btn <?= $isFollowing ? 'btn-secondary' : 'btn-primary' ?>" id="btn-follow">
            <?= $isFollowing ? 'フォロー解除' : 'フォローする' ?>
        </button>
      <?php endif; ?>
    </div>

    <?php if ($isOwnProfile): ?>
    <div style="margin-top: 15px; display: flex; justify-content: center;">
      <a href="bot.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; border-color: var(--border-brand); background: var(--brand-dim); color: var(--brand); font-weight: 700; text-decoration: none; padding: 10px 24px; border-radius: 20px;">
        <i class="fas fa-brain"></i> 思考の分身チャットを開く
      </a>
    </div>
    <?php endif; ?>

    <?php if ($isOwnProfile): ?>
    <div style="margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px;">
      <span style="font-size: 0.9rem; color: var(--text-secondary);">テーマ切替:</span>
      <button class="theme-toggle" id="themeToggleBtn" title="ダーク/ライト切替" aria-label="テーマ切替" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-primary);">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <?php if (count($visiblePosts) === 0): ?>
    <div class="empty-state">
      <i class="fas fa-microphone-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
      <p>まだ投稿がありません</p>
    </div>
  <?php else: ?>
    <div class="feed" id="feed" style="padding: 0 15px;">
      <?php foreach (array_reverse($visiblePosts, true) as $i => $post): ?>
        <?php
          $postId      = $post['id'] ?? uniqid();
          $postTitle   = $post['title'] ?? '';
          $postDate    = $post['date'] ?? '';
          $postText    = $post['text'] ?? '';
          $postSummary = $post['summary'] ?? '';
          
          $af          = $post['audio_file'] ?? $post['audio'] ?? '';
          $audioSrc    = !empty($af) ? '../audio_proxy.php?target_uid=' . urlencode($targetUid) . '&path=' . urlencode($af) : '';
          
          $likes       = $post['likes'] ?? [];
          $likeCount   = count($likes);
          $isLiked     = in_array($uid, $likes);
          $commentCount = $post['comment_count'] ?? 0;
          
          // Check saved status
          $savedFile = $userDir . $uid . '_saved.json';
          $savedItems = file_exists($savedFile) ? (json_decode(file_get_contents($savedFile), true) ?: []) : [];
          $isSaved = false;
          foreach ($savedItems as $sItem) {
              if (($sItem['post_id'] ?? '') === $postId && ($sItem['author_uid'] ?? '') === $targetUid) {
                  $isSaved = true;
                  break;
              }
          }
          
          $displayCaption = $postSummary ?: mb_strimwidth(strip_tags($postText), 0, 120, '…');
          $hasFullText = !empty($postText) && mb_strlen(strip_tags($postText)) > 50;
        ?>
        <article class="post-card" id="post-card-<?= htmlspecialchars($postId) ?>" style="margin-bottom: 20px;">
          
          <div class="post-header">
            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="post-avatar">
            <div class="post-header__info">
              <div class="post-username"><?= htmlspecialchars($displayName) ?></div>
              <div class="post-date"><?= htmlspecialchars($postDate) ?></div>
            </div>
          </div>

          <?php if (!empty($displayCaption)): ?>
          <div class="post-caption collapsed" id="caption-<?= htmlspecialchars($postId) ?>">
            <strong><?= htmlspecialchars($postTitle) ?></strong><br>
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
              <a href="view_post.php?uid=<?= urlencode($targetUid) ?>&index=<?= $i ?>" class="btn btn-ghost btn-sm" style="padding-left:0;">
                <i class="fas fa-external-link-alt"></i> 詳細ページを開く
              </a>
            </div>
          </div>
          <?php endif; ?>
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

          <div class="post-actions">
            <button class="action-btn <?= $isLiked ? 'liked' : '' ?>" onclick="toggleLike('<?= htmlspecialchars($targetUid) ?>', <?= $i ?>, this)" id="like-btn-<?= htmlspecialchars($postId) ?>">
              <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart"></i>
              <span class="like-count"><?= $likeCount > 0 ? $likeCount : '' ?></span>
            </button>
            <a href="view_post.php?uid=<?= urlencode($targetUid) ?>&index=<?= $i ?>#comments" class="action-btn">
              <i class="far fa-comment"></i>
              <span><?= $commentCount > 0 ? $commentCount : '' ?></span>
            </a>
            <button class="action-btn <?= $isSaved ? 'saved' : '' ?>" onclick="toggleSave('<?= htmlspecialchars($targetUid) ?>', '<?= htmlspecialchars($postId) ?>', this)" id="save-btn-<?= htmlspecialchars($postId) ?>">
              <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-bookmark"></i>
            </button>
            <a href="view_post.php?uid=<?= urlencode($targetUid) ?>&index=<?= $i ?>" class="action-btn action-btn--edit" title="詳細">
              <i class="fas fa-chevron-right"></i>
            </a>
          </div>

        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<nav class="app-nav">
  <a href="mypage.php" class="nav-item">
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
  <a href="profile.php" class="nav-item active">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>

<!-- Toast (global) -->
<div class="toast" id="globalToast"></div>

<script>
/* =============================================================
   Theme toggle
   ============================================================= */
(function() {
  const saved = localStorage.getItem('udatsu_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
})();

function updateThemeIcon(theme) {
  const icon = document.getElementById('themeIcon');
  if (!icon) return;
  icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
}

const themeToggleBtn = document.getElementById('themeToggleBtn');
if (themeToggleBtn) {
  themeToggleBtn.addEventListener('click', function() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('udatsu_theme', next);
    updateThemeIcon(next);
  });
}

/* =============================================================
   Toast helper
   ============================================================= */
function showToast(msg, type='') {
  const t = document.getElementById('globalToast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast ' + type + ' show';
  setTimeout(() => { t.classList.remove('show'); }, 3000);
}

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
    audio.play().catch(e => console.warn(e));
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
   Read more / Expand & Markdown Parse
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
   Like / Save Actions (optimistic UI)
   ============================================================= */
function toggleLike(authorUid, postIndex, btn) {
  const icon = btn.querySelector('i');
  const countSpan = btn.querySelector('.like-count');
  if (!btn) return;
  
  const liked = btn.classList.toggle('liked');
  icon.className = liked ? 'fas fa-heart' : 'far fa-heart';
  if (countSpan) {
    const cur = parseInt(countSpan.textContent) || 0;
    countSpan.textContent = liked ? (cur + 1) || '1' : (cur - 1) > 0 ? (cur - 1) : '';
  }

  const fd = new FormData();
  fd.append('action', liked ? 'like' : 'unlike');
  fd.append('target_uid', authorUid);
  fd.append('post_index', postIndex);

  fetch('submit_interaction.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        const likedServer = (data.interaction === 'liked');
        btn.classList.toggle('liked', likedServer);
        icon.className = likedServer ? 'fas fa-heart' : 'far fa-heart';
        if (countSpan) {
          countSpan.textContent = data.count > 0 ? data.count : '';
        }
      }
    }).catch(e => console.error(e));
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
   Follow toggle
   ============================================================= */
function toggleFollow(targetUid, isCurrentlyFollowing) {
    const action = isCurrentlyFollowing ? 'unfollow' : 'follow';
    fetch('api_follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, targetUid: targetUid })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Communication error'));
}
</script>
</body>
</html>