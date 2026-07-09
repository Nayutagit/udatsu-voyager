<?php
session_start();
if (!isset($_SESSION['uid'])) {
  header("Location: login.php");
  exit();
}
$myUid     = $_SESSION['uid'];
$targetUid = isset($_GET['uid']) ? $_GET['uid'] : $myUid;
$isOwner   = ($myUid === $targetUid);
$userName  = $_SESSION['userName'] ?? '';
$userPlan  = $_SESSION['userPlan'] ?? 'free';

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';
$id        = isset($_GET['id']) ? $_GET['id'] : '';
$index     = isset($_GET['index']) ? intval($_GET['index']) : -1;

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) $posts = [];

// Assign IDs if missing
$postsUpdated = false;
foreach ($posts as &$p) {
  if (empty($p['id'])) { $p['id'] = uniqid('post_'); $postsUpdated = true; }
}
unset($p);
if ($postsUpdated) file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Find post
if ($id !== '') {
  foreach ($posts as $k => $p) {
    if (($p['id'] ?? '') === $id) { $index = $k; break; }
  }
}

if ($index < 0 || !isset($posts[$index])) {
  die("投稿が見つかりません。");
}
$post     = $posts[$index];
$title    = $post['title'] ?? '';
$text     = $post['text'] ?? '';
$summary  = $post['summary'] ?? '';
$date     = $post['date'] ?? '';
$category = $post['category'] ?? '';
$audio    = $post['audio_file'] ?? $post['audio'] ?? '';

$audioSrc    = !empty($audio) ? "../audio_proxy.php?target_uid=" . urlencode($targetUid) . "&path=" . urlencode($audio) : '';
$downloadSrc = !empty($audio) ? "../audio_proxy.php?target_uid=" . urlencode($targetUid) . "&path=" . urlencode($audio) . "&download=1" : '';

$profileFile = $userDir . $targetUid . '_profile.json';
$profile = ['display_name' => $userName, 'image' => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'] ?? $userName;
$imagePath   = !empty($profile['image']) ? '../uploads/' . $targetUid . '/' . $profile['image'] : '../img/default-icon.png';
?>
<!DOCTYPE html>
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= htmlspecialchars($title) ?> | Udatsu</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <style>
    /* View Post specific */
    .vp-container {
      max-width: 600px;
      margin: 0 auto;
      padding: 0;
    }
    .vp-card {
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      padding: 20px 16px;
    }
    .vp-title {
      font-size: 1.2rem;
      font-weight: 800;
      line-height: 1.4;
      color: var(--text-primary);
      margin-bottom: 6px;
    }
    .vp-meta {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .vp-summary {
      background: var(--brand-dim);
      border-left: 3px solid var(--brand);
      border-radius: 0 8px 8px 0;
      padding: 14px 16px;
      margin-bottom: 16px;
      font-size: 0.88rem;
      line-height: 1.75;
      color: var(--text-primary);
    }
    .vp-summary h4 {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--brand);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 8px;
    }
    .vp-article {
      font-size: 0.9rem;
      line-height: 1.85;
      color: var(--text-primary);
      word-break: break-word;
    }
    .vp-article h1, .vp-article h2, .vp-article h3 {
      color: var(--text-primary);
      font-weight: 700;
      margin: 1.4em 0 0.6em;
      padding-bottom: 6px;
      border-bottom: 1px solid var(--border);
    }
    .vp-article h1 { font-size: 1.15rem; }
    .vp-article h2 { font-size: 1.05rem; }
    .vp-article h3 { font-size: 0.97rem; }
    .vp-article p  { margin-bottom: 0.9em; }
    .vp-article strong { color: var(--brand); }
    .vp-article blockquote {
      border-left: 3px solid var(--brand-dim);
      padding: 10px 14px;
      color: var(--text-secondary);
      margin: 12px 0;
      border-radius: 0 6px 6px 0;
      background: var(--bg-input);
    }
    .vp-article ul, .vp-article ol {
      padding-left: 1.4em;
      margin-bottom: 0.9em;
    }
    .vp-article li { margin-bottom: 0.3em; }
    .vp-article code {
      background: var(--bg-input);
      padding: 1px 5px;
      border-radius: 4px;
      font-size: 0.85em;
    }
    .vp-edit-toolbar {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 16px;
    }
    .vp-audio {
      background: var(--bg-input);
      border-radius: 28px;
      padding: 10px 14px 10px 10px;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }
  </style>
</head>
<body>

<!-- Header -->
<header class="app-header">
  <a href="javascript:void(0);" onclick="goBackOrHome()" class="icon-btn" style="color:var(--text-primary);" aria-label="戻る">
    <i class="fas fa-arrow-left"></i>
  </a>
  <a href="mypage.php" class="app-header__logo" style="text-decoration: none; display: flex; align-items: center; margin-left: 10px;">
    <img src="../img/udatsu-logo.png" alt="Udatsu" style="height: 28px; filter: drop-shadow(0 0 5px rgba(252,200,0,0.5));">
  </a>
  <div class="app-header__actions">
    <!-- AI Chat Bot Link -->
    <a href="bot.php" class="icon-btn" aria-label="AIチャット" title="思考の分身 AIチャット">
      <i class="fas fa-brain" style="color: var(--brand); text-shadow: 0 0 8px var(--brand-glow);"></i>
    </a>
  </div>
</header>

<main class="app-main">
<div class="vp-container">

  <!-- Author + title card -->
  <div class="vp-card">
    <div class="post-header" style="margin-bottom:12px;">
      <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="post-avatar">
      <div class="post-header__info">
        <div class="post-username"><?= htmlspecialchars($displayName) ?></div>
        <div class="post-date"><?= htmlspecialchars($date) ?></div>
      </div>
      <?php if ($isOwner): ?>
      <a href="edit_post.php?id=<?= htmlspecialchars($post['id'] ?? '') ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-edit"></i> 編集
      </a>
      <?php endif; ?>
    </div>

    <!-- Edit toolbar (owner only) -->
    <?php if ($isOwner): ?>
    <div class="vp-edit-toolbar" id="editToolbar" style="display:none;">
      <button onclick="savePost()" id="btn-save" class="btn btn-primary btn-sm">
        <i class="fas fa-save"></i> 保存
      </button>
      <button onclick="cancelEdit()" class="btn btn-secondary btn-sm">
        <i class="fas fa-times"></i> キャンセル
      </button>
    </div>
    <?php endif; ?>

    <div id="view-title" class="vp-title"><?= htmlspecialchars($title) ?></div>
    <input id="edit-title" type="text" value="<?= htmlspecialchars($title) ?>" class="form-control" style="display:none;margin-bottom:8px;font-weight:700;">

    <div class="vp-meta">
      <?php if ($category): ?><span><i class="fas fa-tag"></i> <?= htmlspecialchars($category) ?></span><?php endif; ?>
      <button onclick="copyContent()" class="btn btn-ghost btn-sm" style="margin-left:auto;color:var(--text-muted);">
        <i class="far fa-copy"></i> コピー
      </button>
    </div>

    <!-- Audio player -->
    <?php if (!empty($audioSrc)): ?>
    <div class="vp-audio">
      <button class="audio-play-btn" onclick="toggleAudioVP()" id="vp-play-btn" aria-label="再生">
        <i class="fas fa-play" id="vp-play-icon"></i>
      </button>
      <div class="audio-progress-wrapper">
        <input type="range" class="audio-progress" id="vp-progress" value="0" min="0" max="100" step="0.1"
               oninput="seekAudioVP(this.value)">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span class="audio-time" id="vp-time">0:00 / --:--</span>
          <a href="<?= htmlspecialchars($downloadSrc) ?>" class="btn btn-ghost btn-sm" style="font-size:0.7rem;color:var(--text-muted);">
            <i class="fas fa-download"></i>
          </a>
        </div>
      </div>
      <audio id="vp-audio" src="<?= htmlspecialchars($audioSrc) ?>" preload="none"
             ontimeupdate="updateProgressVP()" onended="audioEndedVP()"></audio>
    </div>
    <?php endif; ?>

    <!-- Summary -->
    <?php if (!empty($summary)): ?>
    <div class="vp-summary">
      <h4><i class="fas fa-magic"></i> AI要約</h4>
      <div id="summary-content"><?= htmlspecialchars($summary) ?></div>
    </div>
    <?php endif; ?>

    <!-- Full article -->
    <div id="view-text" class="vp-article"><?= htmlspecialchars($text) ?></div>
    <textarea id="edit-text" class="form-control" style="display:none;min-height:50vh;resize:vertical;margin-top:8px;font-size:0.88rem;line-height:1.8;"><?= htmlspecialchars($text) ?></textarea>

  </div><!-- /vp-card -->

  <!-- Actions -->
  <div style="padding:16px;display:flex;gap:12px;align-items:center;">
    <a href="mypage.php" class="btn btn-secondary btn-sm">
      <i class="fas fa-arrow-left"></i> マイページ
    </a>
    <?php if ($isOwner): ?>
    <button onclick="toggleEdit()" id="btn-edit" class="btn btn-ghost btn-sm" style="margin-left:auto;">
      <i class="fas fa-edit"></i> 編集する
    </button>
    <?php endif; ?>
  </div>

</div>
</main>

<!-- Bottom nav -->
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
    <div class="nav-record"><i class="fas fa-microphone"></i></div>
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

<div class="toast" id="globalToast"></div>

<script>
/* Theme */
(function() {
  const t = localStorage.getItem('udatsu_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);
})();

/* Toast */
function showToast(msg, type='') {
  const t = document.getElementById('globalToast');
  t.textContent = msg;
  t.className = 'toast ' + type + ' show';
  setTimeout(() => t.classList.remove('show'), 3000);
}

/* Markdown render */
function renderMarkdown() {
  const vt = document.getElementById('view-text');
  if (vt && typeof marked !== 'undefined') {
    const raw = vt.textContent.trim();
    if (raw) vt.innerHTML = marked.parse(raw);
  }
  const sc = document.getElementById('summary-content');
  if (sc && typeof marked !== 'undefined') {
    const raw = sc.textContent.trim();
    if (raw) sc.innerHTML = marked.parse(raw);
  }
}
if (typeof marked !== 'undefined') renderMarkdown();
else document.querySelector('script[src*="marked"]').addEventListener('load', renderMarkdown);

/* Audio (view post) */
function toggleAudioVP() {
  const audio = document.getElementById('vp-audio');
  const icon  = document.getElementById('vp-play-icon');
  if (!audio) return;
  if (audio.paused) {
    audio.addEventListener('loadedmetadata', function onMeta() {
      updateProgressVP();
      audio.removeEventListener('loadedmetadata', onMeta);
    });
    audio.play().catch(e => console.warn(e));
    icon.className = 'fas fa-pause';
  } else {
    audio.pause();
    icon.className = 'fas fa-play';
  }
}
function updateProgressVP() {
  const audio = document.getElementById('vp-audio');
  const prog  = document.getElementById('vp-progress');
  const time  = document.getElementById('vp-time');
  if (!audio) return;
  const pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
  if (prog) prog.value = pct;
  const cur = fmtTime(audio.currentTime);
  const dur = (audio.duration && !isNaN(audio.duration)) ? fmtTime(audio.duration) : '--:--';
  if (time) time.textContent = cur + ' / ' + dur;
}
function seekAudioVP(val) {
  const audio = document.getElementById('vp-audio');
  if (audio && audio.duration) audio.currentTime = (val / 100) * audio.duration;
}
function audioEndedVP() {
  const icon = document.getElementById('vp-play-icon');
  const prog = document.getElementById('vp-progress');
  if (icon) icon.className = 'fas fa-play';
  if (prog) prog.value = 0;
}
function fmtTime(sec) {
  if (isNaN(sec)) return '0:00';
  return Math.floor(sec/60) + ':' + Math.floor(sec%60).toString().padStart(2,'0');
}

/* Edit mode */
function toggleEdit() {
  document.getElementById('view-title').style.display = 'none';
  document.getElementById('edit-title').style.display = 'block';
  document.getElementById('view-text').style.display  = 'none';
  document.getElementById('edit-text').style.display  = 'block';
  document.getElementById('editToolbar').style.display = 'flex';
  document.getElementById('btn-edit').style.display   = 'none';
}
function cancelEdit() {
  document.getElementById('view-title').style.display = 'block';
  document.getElementById('edit-title').style.display = 'none';
  document.getElementById('view-text').style.display  = 'block';
  document.getElementById('edit-text').style.display  = 'none';
  document.getElementById('editToolbar').style.display = 'none';
  document.getElementById('btn-edit').style.display   = 'inline-flex';
}
async function savePost() {
  const btn = document.getElementById('btn-save');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  btn.disabled = true;
  const fd = new FormData();
  fd.append('index', <?= $index ?>);
  fd.append('title', document.getElementById('edit-title').value);
  fd.append('text',  document.getElementById('edit-text').value);
  try {
    const res = await fetch('save_post_ajax.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.status === 'ok') {
      const newTitle = document.getElementById('edit-title').value;
      const newText  = document.getElementById('edit-text').value;
      document.getElementById('view-title').textContent = newTitle;
      document.getElementById('view-text').innerHTML = typeof marked !== 'undefined' ? marked.parse(newText) : newText;
      document.title = newTitle + ' | Udatsu';
      cancelEdit();
      showToast('保存しました', 'success');
    } else {
      showToast(data.message || '保存に失敗しました', 'error');
    }
  } catch(e) {
    showToast('通信エラーが発生しました', 'error');
  } finally {
    btn.innerHTML = '<i class="fas fa-save"></i> 保存';
    btn.disabled = false;
  }
}

/* Copy */
async function copyContent() {
  const title = document.getElementById('view-title').textContent;
  const text  = document.getElementById('view-text').innerText;
  try {
    await navigator.clipboard.writeText(title + '\n\n' + text);
    showToast('コピーしました', 'success');
  } catch(e) {
    showToast('コピーに失敗しました', 'error');
  }
}

function goBackOrHome() {
  if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
    history.back();
  } else {
    window.location.href = 'mypage.php';
  }
}
</script>
</body>
</html>