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
</head>
<body>

<!-- ============================================================
     APP HEADER
     ============================================================ -->
<header class="app-header">
  <span class="app-header__logo">Üdatsu</span>
  <div class="app-header__actions">
    <!-- Dark/Light toggle -->
    <button class="theme-toggle" id="themeToggleBtn" title="ダーク/ライト切替" aria-label="テーマ切替">
      <i class="fas fa-moon" id="themeIcon"></i>
    </button>
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
    <?php
      $postId     = $post['id'] ?? '';
      $postStatus = $post['status'] ?? '';
      $postTitle  = $post['title'] ?? '';
      $postDate   = $post['date'] ?? '';
      $postSummary= $post['summary'] ?? '';
      $postText   = $post['text'] ?? '';
      $af         = $post['audio_file'] ?? $post['audio'] ?? '';
      $audioSrc   = !empty($af) ? '../audio_proxy.php?target_uid=' . urlencode($uid) . '&path=' . urlencode($af) : '';
      $likeCount  = $post['like_count'] ?? 0;
      $commentCount = $post['comment_count'] ?? 0;
      $isLiked    = false; // TODO: check per-user
      $isShared   = !empty($post['is_shared']);
      $isAnalyzing= ($postStatus === '解析中');
      $isError    = ($postStatus === 'エラー' || (empty($postSummary) && empty($postText) && !empty($af) && !$isAnalyzing));
      $displayCaption = $postSummary ?: mb_strimwidth(strip_tags($postText), 0, 120, '…');
      $hasFullText = !empty($postText) && mb_strlen(strip_tags($postText)) > 50;
    ?>
    <article class="post-card" id="post-card-<?= htmlspecialchars($postId) ?>" data-id="<?= htmlspecialchars($postId) ?>">

      <!-- Post header -->
      <div class="post-header">
        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="post-avatar">
        <div class="post-header__info">
          <div class="post-username"><?= htmlspecialchars($displayName) ?><?= !empty($networkId) ? ' <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400;">@'.htmlspecialchars($networkId).'</span>' : '' ?></div>
          <div class="post-date"><?= htmlspecialchars($postDate) ?></div>
        </div>
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
      </div>

      <!-- Status badge -->
      <?php if ($isAnalyzing): ?>
      <div class="post-status-badge badge-analyzing">
        <i class="fas fa-spinner fa-spin"></i> 解析中...
      </div>
      <?php elseif ($isError): ?>
      <div class="post-status-badge badge-error" onclick="doAction('<?= htmlspecialchars($postId) ?>', 'retry')" title="タップして再解析">
        <i class="fas fa-exclamation-triangle"></i> 解析未完了・タップして再試行
      </div>
      <?php endif; ?>

      <!-- Audio player -->
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
               preload="none"
               ontimeupdate="updateProgress('<?= htmlspecialchars($postId) ?>')"
               onended="audioEnded('<?= htmlspecialchars($postId) ?>')">
        </audio>
      </div>
      <?php endif; ?>

      <!-- Caption (collapsed 3 lines) -->
      <?php if (!empty($displayCaption) && !$isAnalyzing): ?>
      <div class="post-caption collapsed" id="caption-<?= htmlspecialchars($postId) ?>">
        <?= nl2br(htmlspecialchars($displayCaption)) ?>
      </div>

      <?php if ($hasFullText): ?>
      <button class="post-read-more" id="readmore-<?= htmlspecialchars($postId) ?>"
              onclick="expandPost('<?= htmlspecialchars($postId) ?>')">
        続きを読む
      </button>
      <?php endif; ?>

      <!-- Full text (hidden initially) -->
      <?php if ($hasFullText): ?>
      <div class="post-full-text" id="fulltext-<?= htmlspecialchars($postId) ?>" style="display:none;">
        <?= nl2br(htmlspecialchars(strip_tags($postText))) ?>
        <div style="margin-top:12px;">
          <a href="view_post.php?id=<?= htmlspecialchars($postId) ?>" class="btn btn-ghost btn-sm" style="padding-left:0;">
            <i class="fas fa-external-link-alt"></i> 詳細ページを開く
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; ?>

      <!-- Actions -->
      <div class="post-actions">
        <button class="action-btn <?= $isLiked ? 'liked' : '' ?>" onclick="toggleLike('<?= htmlspecialchars($postId) ?>')" id="like-btn-<?= htmlspecialchars($postId) ?>">
          <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart" id="like-icon-<?= htmlspecialchars($postId) ?>"></i>
          <span id="like-count-<?= htmlspecialchars($postId) ?>"><?= $likeCount > 0 ? $likeCount : '' ?></span>
        </button>
        <a href="view_post.php?id=<?= htmlspecialchars($postId) ?>#comments" class="action-btn">
          <i class="far fa-comment"></i>
          <span><?= $commentCount > 0 ? $commentCount : '' ?></span>
        </a>
        <button class="action-btn" onclick="toggleSave('<?= htmlspecialchars($postId) ?>')" id="save-btn-<?= htmlspecialchars($postId) ?>">
          <i class="far fa-bookmark" id="save-icon-<?= htmlspecialchars($postId) ?>"></i>
        </button>
        <a href="view_post.php?id=<?= htmlspecialchars($postId) ?>" class="action-btn action-btn--edit" title="詳細">
          <i class="fas fa-chevron-right"></i>
        </a>
      </div>

    </article>
    <?php endforeach; ?>
    <?php endif; ?>

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
    <span>探す</span>
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

document.getElementById('themeToggleBtn').addEventListener('click', function() {
  const html = document.documentElement;
  const current = html.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('udatsu_theme', next);
  updateThemeIcon(next);
});

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
  // Close all menus first
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

function toggleAudio(postId) {
  const audio = document.getElementById('audio-' + postId);
  const icon  = document.getElementById('play-icon-' + postId);
  if (!audio) return;

  // Pause any other playing audio
  if (currentAudioId && currentAudioId !== postId) {
    const prev = document.getElementById('audio-' + currentAudioId);
    const prevIcon = document.getElementById('play-icon-' + currentAudioId);
    if (prev) prev.pause();
    if (prevIcon) prevIcon.className = 'fas fa-play';
    currentAudioId = null;
  }

  if (audio.paused) {
    // Add loadedmetadata listener to update duration once loaded
    audio.addEventListener('loadedmetadata', function onMeta() {
      updateProgress(postId);
      audio.removeEventListener('loadedmetadata', onMeta);
    });
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
   Read more / expand
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
    fulltext.style.display = 'block';
    if (caption) caption.classList.remove('collapsed');
    if (btn) btn.textContent = '閉じる';
  }
}

/* =============================================================
   Like / Save (optimistic UI)
   ============================================================= */
function toggleLike(postId) {
  const btn  = document.getElementById('like-btn-' + postId);
  const icon = document.getElementById('like-icon-' + postId);
  const count= document.getElementById('like-count-' + postId);
  if (!btn) return;

  const liked = btn.classList.toggle('liked');
  icon.className = liked ? 'fas fa-heart' : 'far fa-heart';
  const cur = parseInt(count.textContent) || 0;
  count.textContent = liked ? (cur + 1) || '' : (cur - 1) > 0 ? (cur - 1) : '';

  const fd = new FormData();
  fd.append('id', postId);
  fd.append('action', liked ? 'like' : 'unlike');
  fetch('submit_interaction.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .catch(() => {});
}

function toggleSave(postId) {
  const btn  = document.getElementById('save-btn-' + postId);
  const icon = document.getElementById('save-icon-' + postId);
  if (!btn) return;
  const saved = btn.classList.toggle('saved');
  icon.className = saved ? 'fas fa-bookmark' : 'far fa-bookmark';
  showToast(saved ? '保存しました' : '保存を解除しました', saved ? 'success' : '');

  const fd = new FormData();
  fd.append('id', postId);
  fd.append('action', saved ? 'save' : 'unsave');
  fetch('submit_interaction.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .catch(() => {});
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

