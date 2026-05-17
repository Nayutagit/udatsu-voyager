<?php
session_start();
if (!isset($_SESSION['uid'])) {
  header("Location: login.php");
  exit();
}
$myUid = $_SESSION['uid'];
$targetUid = isset($_GET['uid']) ? $_GET['uid'] : $myUid;
$isOwner = ($myUid === $targetUid);

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';
$index = isset($_GET['index']) ? intval($_GET['index']) : -1;
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

if ($index < 0 || !isset($posts[$index])) {
  die("投稿が見つかりません。");
}
$post = $posts[$index];
$title = $post['title'] ?? '';
$text = $post['text'] ?? '';
$date = $post['date'] ?? '';
$category = $post['category'] ?? '';
$audio = $post['audio'] ?? '';
$thumbnail = $post['thumbnail'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($title); ?> | Udatsu投稿詳細</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <style>
    /* Page specific styles */
    .container {
      max-width: 800px;
      margin: 0 auto;
      position: relative;
    }
    header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 3rem;
      padding: 1rem 0;
      animation: slideDown 1s ease;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    header img.logo {
      height: 52px;
      transition: all 0.4s ease;
      filter: drop-shadow(0 0 8px rgba(252, 200, 0, 0.6));
    }
    header img.logo:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 0 15px rgba(252, 200, 0, 0.6));
    }
    header a {
      color: var(--text-white);
      font-weight: 700;
      font-size: 1.3rem;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    header a:hover {
      color: var(--primary-neon);
    }
    .post-container {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3rem;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      margin-bottom: 3rem;
      transition: all 0.4s ease;
      animation: fadeInUp 1s ease 0.3s both;
      position: relative;
      overflow: hidden;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .post-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--primary-neon), transparent);
      transition: left 0.4s ease;
    }
    .post-container:hover::before {
      left: 100%;
    }
    .post-container:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    .post-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--text-white);
      line-height: 1.4;
    }
    .post-meta {
      font-size: 1rem;
      color: var(--text-muted);
      margin-bottom: 2rem;
      padding: 1rem;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .thumbnail {
      width: 100%;
      height: auto;
      border-radius: 16px;
      margin-bottom: 2rem;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      transition: all 0.4s ease;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .thumbnail:hover {
      transform: scale(1.02);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    audio {
      width: 100%;
      margin-bottom: 2rem;
      border-radius: 12px;
      background: rgba(42, 42, 46, 0.95);
      padding: 1rem;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    }
    audio::-webkit-media-controls-panel {
      background-color: rgba(42, 42, 46, 0.95);
    }
    .post-text {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
      background: rgba(255, 255, 255, 0.05);
      padding: 2.5rem;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    /* Markdown Styling (Apply to both text and summary) */
    .markdown-body h1, .markdown-body h2, .markdown-body h3 {
      color: var(--text-white);
      margin-top: 1.5rem;
      margin-bottom: 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding-bottom: 0.5rem;
    }
    .markdown-body h1 { font-size: 1.6rem; }
    .markdown-body h2 { font-size: 1.4rem; }
    .markdown-body h3 { font-size: 1.2rem; }
    .markdown-body p { margin-bottom: 1rem; line-height: 1.8; }
    .markdown-body strong { color: var(--primary-neon); font-weight: 700; }
    .markdown-body ul, .markdown-body ol { margin-bottom: 1rem; padding-left: 1.5rem; }
    .markdown-body li { margin-bottom: 0.4rem; }
    .markdown-body blockquote {
      border-left: 4px solid var(--primary-neon);
      padding-left: 1rem;
      margin: 1.5rem 0;
      color: var(--text-white);
      font-style: italic;
      background: rgba(255, 255, 255, 0.03);
      padding: 1rem;
      border-radius: 0 8px 8px 0;
    }
    .markdown-body code {
      background: rgba(255, 255, 255, 0.1);
      padding: 0.2rem 0.4rem;
      border-radius: 4px;
      font-family: monospace;
    }
    .back-button {
      text-align: center;
      animation: fadeInUp 1s ease 0.6s both;
    }
    .back-button a {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 1rem 2.5rem;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: inline-block;
    }
    .back-button a::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(45deg, var(--primary-neon), #ffda44);
      transition: left 0.3s ease;
      z-index: -1;
    }
    .back-button a:hover::before {
      left: 0;
    }
    .back-button a:hover {
      color: #000;
      border-color: var(--primary-neon);
      transform: translateY(-3px);
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.6);
    }
    @media (max-width: 768px) {
      body {
        padding: 0; /* Remove body padding on mobile to maximize width */
      }
      .container {
        padding-top: 80px !important;
        padding-left: 10px !important;
        padding-right: 10px !important;
      }
      .post-container {
        padding: 1.5rem 1rem; /* Reduce internal padding on mobile */
        border-radius: 0; /* Optional: edge-to-edge feel */
        border-left: none;
        border-right: none;
      }
      header {
        padding: 1rem;
        flex-direction: row;
        justify-content: space-between;
        text-align: left;
      }
      header img.logo {
        height: 36px;
      }
      .post-title {
        font-size: 1.4rem;
      }
      .post-text {
        padding: 1.5rem 1rem;
        background: rgba(255, 255, 255, 0.03);
        border: none;
        font-size: 1.1rem;
        color: #eee;
        line-height: 1.9;
      }
      .post-text h1 { font-size: 1.5rem; }
      .post-text h2 { font-size: 1.3rem; }
      .summary-box {
        padding: 1.2rem !important;
        margin-bottom: 2rem !important;
      }
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <a href="mypage.php">
        <img src="../img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
      </a>
      <a href="mypage.php">Udatsuマイページ</a>
    </header>
    
    <div class="post-container">
      <!-- アクションバー -->
      <div style="display: <?= $isOwner ? 'flex' : 'none' ?>; justify-content: flex-end; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; flex-wrap: wrap;">
        <button onclick="copyContent(this)" id="btn-copy" class="btn-small" style="background: rgba(0,255,204,0.05); color: var(--primary-neon); border: 1px solid rgba(0,255,204,0.4); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 6px;">
          <i class="far fa-copy"></i> コピー
        </button>
        <button onclick="toggleEdit()" id="btn-edit" class="btn-small" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 6px;">
          <i class="far fa-edit"></i> 編集
        </button>
        <button onclick="savePost()" id="btn-save" style="display:none; background: var(--primary-neon); color: #000; border: none; border-radius: 8px; padding: 8px 16px; cursor: pointer; font-size: 0.85rem; font-weight: 700; transition: 0.3s; align-items: center; gap: 6px;">
          <i class="fas fa-save"></i> 保存
        </button>
        <button onclick="cancelEdit()" id="btn-cancel" style="display:none; background: rgba(255,68,68,0.1); color: #ff4444; border: 1px solid rgba(255,68,68,0.3); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.3s; align-items: center; gap: 6px;">
          <i class="fas fa-times"></i> キャンセル
        </button>
      </div>

      <?php if (!$isOwner): ?>
      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; flex-wrap: wrap;">
        <button onclick="copyContent(this)" id="btn-copy" class="btn-small" style="background: rgba(0,255,204,0.05); color: var(--primary-neon); border: 1px solid rgba(0,255,204,0.4); border-radius: 8px; padding: 8px 14px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 6px;">
          <i class="far fa-copy"></i> コピー
        </button>
      </div>
      <?php endif; ?>

      <!-- タイトル（表示/編集切り替え） -->
      <div id="view-title" class="post-title"><?php echo htmlspecialchars($title); ?></div>
      <input id="edit-title" type="text" value="<?php echo htmlspecialchars($title); ?>" style="display:none; width:100%; font-size:1.4rem; font-weight:700; background:rgba(255,255,255,0.08); border:1px solid rgba(252,200,0,0.5); border-radius:10px; padding:10px 14px; color:#fff; margin-bottom:1rem; box-sizing:border-box;">

      <div class="post-meta"><?php echo htmlspecialchars($date); ?></div>
      
      <?php if ($thumbnail): ?>
        <img class="thumbnail" src="../<?php echo htmlspecialchars($thumbnail); ?>" alt="アイキャッチ画像">
      <?php endif; ?>
      
      <?php if ($audio): ?>
        <?php
          $audioSrc = "";
          if (strpos($audio, 'audio/') === 0 || strpos($audio, 'uploads/') === 0) {
              $audioSrc = "../audio_proxy.php?target_uid=" . urlencode($targetUid) . "&path=" . urlencode($audio);
          } else {
              $audioSrc = "../users/" . urlencode($targetUid) . "/posts/" . urlencode($audio);
          }
        ?>
        <audio controls>
          <source src="<?= htmlspecialchars($audioSrc) ?>" type="audio/mpeg">
          お使いのブラウザは音声再生に対応していません。
        </audio>
      <?php endif; ?>
      
      <?php if (!empty($post['summary'])): ?>
        <div class="summary-box" style="margin-bottom: 2.5rem; background: rgba(252, 200, 0, 0.05); border: 1px solid rgba(252, 200, 0, 0.2); padding: 1.5rem; border-radius: 16px;">
          <h3 style="color: var(--primary-neon); margin-bottom: 1rem; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; border: none; padding: 0;">
            <i class="fas fa-magic"></i> AI要約
          </h3>
          <div id="summary-content" class="markdown-body" style="color: var(--text-white); font-size: 1rem; line-height: 1.7;">
            <?php echo $post['summary']; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- 本文（表示/編集切り替え） -->
      <div id="view-text" class="post-text markdown-body"><?php echo $text; ?></div>
      <textarea id="edit-text" style="display:none; width:100%; min-height:60vh; font-size:1rem; line-height:1.8; background:rgba(255,255,255,0.06); border:1px solid rgba(252,200,0,0.4); border-radius:12px; padding:16px; color:#fff; resize:vertical; box-sizing:border-box; font-family:inherit;"><?php echo htmlspecialchars($text); ?></textarea>
    </div>
    
    <div class="back-button">
      <a href="mypage.php">← マイページに戻る</a>
    </div>
  </div>

  <script>
    const postIndex = <?= $index ?>;
    let isEditing = false;

    // --- Markdown レンダリング ---
    function renderMarkdown() {
      const viewText = document.getElementById('view-text');
      if (viewText) viewText.innerHTML = marked.parse(viewText.textContent.trim());
      const summary = document.getElementById('summary-content');
      if (summary) summary.innerHTML = marked.parse(summary.textContent.trim());
    }
    renderMarkdown();

    // --- 編集モード切り替え ---
    function toggleEdit() {
      isEditing = true;
      document.getElementById('view-title').style.display = 'none';
      document.getElementById('edit-title').style.display = 'block';
      document.getElementById('view-text').style.display  = 'none';
      document.getElementById('edit-text').style.display  = 'block';
      document.getElementById('btn-edit').style.display   = 'none';
      document.getElementById('btn-copy').style.display   = 'none';
      document.getElementById('btn-save').style.display   = 'flex';
      document.getElementById('btn-cancel').style.display = 'flex';
      document.getElementById('edit-title').focus();
    }

    function cancelEdit() {
      isEditing = false;
      document.getElementById('view-title').style.display = 'block';
      document.getElementById('edit-title').style.display = 'none';
      document.getElementById('view-text').style.display  = 'block';
      document.getElementById('edit-text').style.display  = 'none';
      document.getElementById('btn-edit').style.display   = 'flex';
      document.getElementById('btn-copy').style.display   = 'flex';
      document.getElementById('btn-save').style.display   = 'none';
      document.getElementById('btn-cancel').style.display = 'none';
    }

    async function savePost() {
      const saveBtn = document.getElementById('btn-save');
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
      saveBtn.disabled = true;

      const newTitle = document.getElementById('edit-title').value.trim();
      const newText  = document.getElementById('edit-text').value.trim();

      const fd = new FormData();
      fd.append('index', postIndex);
      fd.append('title', newTitle);
      fd.append('text',  newText);

      try {
        const res  = await fetch('save_post_ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') {
          // 表示を更新してビューモードに戻る
          document.getElementById('view-title').textContent = newTitle;
          document.getElementById('view-text').innerHTML = marked.parse(newText);
          document.title = newTitle + ' | Udatsu投稿詳細';
          cancelEdit();
          // 一時的な保存完了トースト
          const toast = document.createElement('div');
          toast.textContent = '✅ 保存しました';
          toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--primary-neon);color:#000;padding:12px 20px;border-radius:8px;font-weight:700;z-index:9999;animation:slideIn 0.3s ease;';
          document.body.appendChild(toast);
          setTimeout(() => toast.remove(), 2500);
        } else {
          alert(data.message || '保存に失敗しました');
        }
      } catch(e) {
        alert('通信エラーが発生しました');
      } finally {
        saveBtn.innerHTML = '<i class="fas fa-save"></i> 保存';
        saveBtn.disabled = false;
      }
    }

    // コピー
    async function copyContent(btn) {
      const title = document.getElementById('view-title').textContent;
      const text  = document.getElementById('view-text').innerText;
      const combined = title + '\n\n' + text;
      try {
        await navigator.clipboard.writeText(combined);
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> コピー完了';
        btn.style.background = 'rgba(0,255,204,0.2)';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
      } catch(err) {
        const ta = document.createElement('textarea');
        ta.value = combined;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) { alert('コピーに失敗しました'); }
        document.body.removeChild(ta);
      }
    }
  </script>
</body>
</html>