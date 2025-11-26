<?php
session_start();

$raw = $_SESSION['raw_transcription'] ?? '（原文なし）';
$audioPath = $_SESSION['audio_path'] ?? '';
$plan = $_SESSION['user_plan'] ?? 'trial';
$uid = $_SESSION['uid'] ?? '';
$name = $_SESSION['user_name'] ?? 'ユーザー';
$displayName = $name ?: 'ユーザー';

$redirectMypage = 'https://udatsu-voyager.com/mypage/dashboard.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>解析結果 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Page specific styles */
    .container {
      max-width: 1000px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
      padding: 2rem;
    }
    .completion-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.9);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      animation: fadeOut 0.3s ease 1.2s forwards;
    }
    .completion-text {
      font-size: 3rem;
      font-weight: 400;
      color: var(--primary-neon);
      text-shadow: 0 0 20px var(--primary-neon);
      animation: glowPulse 1s ease-in-out;
    }
    @keyframes glowPulse {
      0% { opacity: 0; transform: scale(0.8); }
      50% { opacity: 1; transform: scale(1); }
      100% { opacity: 0; transform: scale(0.9) translateY(-100px); }
    }
    @keyframes fadeOut {
      to { opacity: 0; visibility: hidden; }
    }
    .header-login {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 2rem;
      animation: slideDown 1s ease 1.5s both;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .raw-section {
      margin: 3rem auto;
      animation: fadeInUp 1s ease 2.3s both;
    }
    .raw-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .raw-box {
      max-height: 200px;
      overflow-y: auto;
      background: rgba(0, 0, 0, 0.3);
      color: var(--text-white);
      padding: 1.5rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      text-align: left;
      line-height: 1.6;
    }
    .tabs {
      margin: 3rem auto;
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 1rem;
      animation: fadeInUp 1s ease 2.5s both;
    }
    .tabs button {
      padding: 1rem 2rem;
      border: 1px solid var(--accent-teal);
      background: transparent;
      color: var(--text-white);
      border-radius: 50px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .tabs button:hover, .tabs button.active {
      background: var(--accent-teal);
      color: white;
      box-shadow: 0 0 15px rgba(0, 104, 136, 0.4);
    }
    .content-box {
      margin: 2rem auto;
      text-align: left;
      background: rgba(0, 0, 0, 0.3);
      color: var(--text-white);
      border-radius: 4px;
      padding: 2rem;
      min-height: 300px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      line-height: 1.6;
      animation: fadeInUp 1s ease 2.7s both;
    }
    .action-buttons {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      margin: 2rem 0;
      flex-wrap: wrap;
      animation: fadeInUp 1s ease 2.9s both;
    }
    .copy-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(10px);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 8888;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    .copy-modal.show {
      opacity: 1;
      visibility: visible;
    }
    .copy-modal-content {
      background: var(--bg-panel);
      padding: 3rem;
      border-radius: 4px;
      text-align: center;
      border: 1px solid var(--primary-neon);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.2);
    }
    .after-msg {
      margin-top: 3rem;
      font-size: 1rem;
      color: var(--text-muted);
      animation: fadeInUp 1s ease 3.1s both;
      text-align: center;
    }
    .after-msg a {
      color: var(--primary-neon);
    }
  </style>
</head>
<body>

<div class="completion-overlay">
  <div class="completion-text">解析完了</div>
</div>

<div class="container">
  <div class="header-login">
    <span><?= htmlspecialchars($displayName) ?> さん</span>
    <a href="<?= $redirectMypage ?>" class="mypage-btn">マイページ</a>
    <form method="POST" action="mypage/logout.php" style="display:inline;">
      <button type="submit" class="logout-link">ログアウト</button>
    </form>
  </div>

  <h1 onclick="location.href='index.php'" style="cursor: pointer;">🎙️Udatsu Voyager</h1>

  <?php if (file_exists($audioPath)): ?>
    <div class="audio-player" style="text-align: center; margin: 2rem auto;">
      <audio controls preload="auto" style="width: 100%; max-width: 500px;"><source src="<?= htmlspecialchars($audioPath) ?>"></audio>
    </div>
    <p class="note" style="text-align: center; color: var(--text-muted);">⚠️ ページを離れると解析結果が消える可能性があります</p>
  <?php endif; ?>

  <div class="raw-section">
    <div class="raw-header">
      <h3 style="margin: 0;">📝 文字起こし原文</h3>
      <button class="btn" style="padding: 5px 15px; font-size: 0.8rem;" onclick="copyRawText()">📋 原文をコピー</button>
    </div>
    <div class="raw-box" contenteditable="true"><?= nl2br(htmlspecialchars($raw)) ?></div>
  </div>

  <div class="tabs">
    <button onclick="generateText('blog')" data-type="blog">ブログ・note用</button>
    <button onclick="generateText('summary')" data-type="summary">要約</button>
    <button onclick="generateText('podcast')" data-type="podcast">Podcast原稿</button>
  </div>

  <div class="content-box" id="result-area">
    <p id="loading-text" style="text-align: center; color: var(--text-muted); font-style: italic;">整文タイプを選択してください</p>
  </div>

  <div class="action-buttons">
    <button class="btn" onclick="copyText()">📋 コピー</button>
    <button class="btn" onclick="createPost()">📄 記事作成ページへ</button>
  </div>

  <div class="after-msg">
    <p>🛠 外部AIで整えるのもおすすめ：
      <a href="https://chat.openai.com/" target="_blank">ChatGPT</a> /
      <a href="https://claude.ai/" target="_blank">Claude</a> /
      <a href="https://www.notion.so/product/ai" target="_blank">Notion AI</a>
    </p>
  </div>
</div>

<div class="copy-modal" id="copyModal">
  <div class="copy-modal-content">
    <h3 style="color: var(--primary-neon); margin-bottom: 1rem;">📋 コピー完了</h3>
    <p style="margin-bottom: 2rem;">クリップボードにコピーしました！</p>
    <button class="btn" onclick="hideCopyModal()">OK</button>
  </div>
</div>

<script>
const rawText = <?= json_encode($raw) ?>;
let currentContent = '';
let currentType = '';

function generateText(type) {
  const area = document.getElementById('result-area');
  area.innerHTML = '<p id="loading-text" style="text-align: center; animation: pulse 1.5s infinite;">整文中です...</p>';
  
  // タブのアクティブ状態更新
  document.querySelectorAll('.tabs button').forEach(btn => {
    btn.classList.remove('active');
    if (btn.dataset.type === type) {
      btn.classList.add('active');
    }
  });
  
  currentType = type;

  fetch('generate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'type=' + encodeURIComponent(type) + '&raw=' + encodeURIComponent(rawText)
  })
  .then(res => res.text())
  .then(text => {
    currentContent = text;
    area.innerHTML = text.replace(/\n/g, '<br>');
  })
  .catch(() => {
    area.innerHTML = '<p id="loading-text">⚠️ 整文化に失敗しました</p>';
  });
}

function copyText() {
  const content = document.getElementById('result-area').innerText;
  navigator.clipboard.writeText(content).then(() => {
    showCopyModal();
  });
}

function copyRawText() {
  navigator.clipboard.writeText(rawText).then(() => {
    showCopyModal();
  });
}

function showCopyModal() {
  const modal = document.getElementById('copyModal');
  modal.classList.add('show');
}

function hideCopyModal() {
  const modal = document.getElementById('copyModal');
  modal.classList.remove('show');
}

function createPost() {
  if (!currentContent) {
    alert('まず整文タイプを選択してください');
    return;
  }
  
  // タイトルを短く生成（30文字まで）
  let title = currentContent.substring(0, 30).replace(/\n/g, ' ').trim();
  if (title.length >= 30) {
    title += '...';
  }
  
  // セッションストレージに全文を保存
  sessionStorage.setItem('udatsu_auto_title', title);
  sessionStorage.setItem('udatsu_auto_content', currentContent);
  sessionStorage.setItem('udatsu_auto_fill', '1');
  
  // シンプルにページ移動
  window.location.href = 'mypage/create_post.php?auto_redirect=1';
}

// 1.5秒後にオーバーレイを削除
setTimeout(() => {
  const overlay = document.querySelector('.completion-overlay');
  if (overlay) {
    overlay.remove();
  }
}, 1500);
</script>
</body>
</html>