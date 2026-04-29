<?php
session_start();

$raw = $_SESSION['raw_transcription'] ?? '（原文なし）';
$audioPath = $_SESSION['audio_path'] ?? '';
$plan = $_SESSION['user_plan'] ?? 'trial';
$uid = $_SESSION['uid'] ?? '';
$name = $_SESSION['user_name'] ?? 'ユーザー';
$displayName = $name ?: 'ユーザー';

$redirectMypage = 'https://udatsu-voyager.com/mypage/mypage.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Analysis Result | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .hud-container {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 20px;
      margin-top: 20px;
    }
    .hud-main {
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      padding: 20px;
      backdrop-filter: blur(10px);
    }
    .hud-sidebar {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .hud-panel {
      background: rgba(0, 0, 0, 0.6);
      border: 1px solid var(--accent-teal);
      border-radius: 4px;
      padding: 15px;
      position: relative;
    }
    .hud-panel::before {
      content: '';
      position: absolute;
      top: -2px; left: -2px;
      width: 10px; height: 10px;
      border-top: 2px solid var(--primary-neon);
      border-left: 2px solid var(--primary-neon);
    }
    .hud-panel::after {
      content: '';
      position: absolute;
      bottom: -2px; right: -2px;
      width: 10px; height: 10px;
      border-bottom: 2px solid var(--primary-neon);
      border-right: 2px solid var(--primary-neon);
    }
    .hud-title {
      font-family: var(--font-mono);
      font-size: 0.9rem;
      color: var(--accent-teal);
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      border-bottom: 1px solid rgba(0, 164, 216, 0.3);
      padding-bottom: 5px;
    }
    .tab-btn {
      display: block;
      width: 100%;
      padding: 12px;
      margin-bottom: 10px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: var(--text-white);
      text-align: left;
      cursor: pointer;
      transition: all 0.3s ease;
      font-family: var(--font-mono);
      font-size: 0.9rem;
    }
    .tab-btn:hover, .tab-btn.active {
      background: rgba(0, 164, 216, 0.2);
      border-color: var(--accent-teal);
      color: var(--primary-neon);
      padding-left: 20px;
    }
    .tab-btn.active::before {
      content: '►';
      margin-right: 10px;
    }
    @media (max-width: 900px) {
      .hud-container {
        grid-template-columns: 1fr;
      }
    }
    
    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .modal-content {
      background: var(--glass-bg);
      border: 1px solid var(--primary-neon);
      border-radius: 16px;
      transform: scale(0.9);
      transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .modal-overlay.active .modal-content {
      transform: scale(1);
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="header">
    <div class="container header-inner">
      <a href="index.php" class="logo">
        <img src="img/udatsu-logo.png" alt="Udatsu Logo">
        <span>Voyager</span>
      </a>
      <div class="nav-user">
        <span class="text-muted" style="font-size: 0.9rem; margin-right: 10px;">ID: <?= htmlspecialchars($uid) ?></span>
        <a href="<?= $redirectMypage ?>" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.8rem;">
          <i class="fas fa-columns"></i> マイページ
        </a>
      </div>
    </div>
  </header>

  <div class="container" style="padding-top: 100px; padding-bottom: 60px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h1 class="text-neon" style="font-size: 1.8rem; margin: 0;">Analysis Result</h1>
      <div style="font-family: var(--font-mono); color: var(--accent-teal); font-size: 0.9rem;">
        STATUS: <span style="color: var(--primary-neon);">COMPLETE</span>
      </div>
    </div>

    <div class="hud-container">
      
      <!-- Main Content Area -->
      <div class="hud-main">
        <div id="result-area" style="min-height: 400px; line-height: 1.8; font-size: 1.05rem; white-space: pre-wrap;">
          <p class="text-muted" style="text-align: center; padding-top: 100px;">
            <i class="fas fa-arrow-right"></i> Select a format from the right panel to generate content.
          </p>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="hud-sidebar">
        
        <!-- Audio Player -->
        <?php if (file_exists($audioPath)): ?>
        <div class="hud-panel">
          <div class="hud-title">Source Audio</div>
          <audio controls style="width: 100%; height: 30px;">
            <source src="<?= htmlspecialchars($audioPath) ?>">
          </audio>
        </div>
        <?php endif; ?>

        <!-- Raw Text (Editable) -->
        <div class="hud-panel">
          <div class="hud-title">Step 1: Edit Transcription</div>
          <p class="text-muted" style="font-size: 0.75rem; margin-bottom: 5px;">ここで誤字を修正したり、追記できます。</p>
          <textarea id="raw-text-area" style="width: 100%; height: 200px; background: rgba(0,0,0,0.5); border: 1px solid var(--accent-teal); color: var(--text-white); padding: 10px; font-family: var(--font-main); font-size: 0.9rem; border-radius: 4px; resize: vertical; margin-bottom: 10px;"><?= htmlspecialchars($raw) ?></textarea>
          <button class="btn btn-secondary" style="width: 100%; font-size: 0.8rem; padding: 5px;" onclick="copyRawText()">
            <i class="fas fa-copy"></i> 原文をコピー
          </button>
        </div>

        <!-- Generators -->
        <div class="hud-panel">
          <div class="hud-title">Step 2: Generate Asset</div>
          <button class="tab-btn" onclick="generateText('blog')" data-type="blog">YouTube / Note / Thumb</button>
          <button class="tab-btn" onclick="generateText('summary')" data-type="summary">Quick Summary</button>
          <button class="tab-btn" onclick="generateText('podcast')" data-type="podcast">Podcast Script</button>
        </div>

        <!-- Actions -->
        <div class="hud-panel">
          <div class="hud-title">Step 3: Post</div>
          <button class="btn btn-primary" style="width: 100%; margin-bottom: 10px;" onclick="createPost()">
            <i class="fas fa-save"></i> 記録を残す
          </button>
          <button class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;" onclick="copyText()">
            <i class="fas fa-copy"></i> 結果をすべてコピー
          </button>
          <a href="voyager_upload.php" class="btn btn-secondary" style="width: 100%; display: block; text-align: center;">
            <i class="fas fa-undo"></i> 録音に戻る
          </a>
        </div>

      </div>
    </div>

  </div>

  <!-- Copy Modal -->
  <div class="modal-overlay" id="copyModal">
    <div class="modal-content" style="max-width: 400px; text-align: center; padding: 40px;">
      <i class="fas fa-check-circle text-neon" style="font-size: 3rem; margin-bottom: 20px;"></i>
      <h3 style="margin-bottom: 10px;">Copied!</h3>
      <p class="text-muted">Content copied to clipboard.</p>
      <button class="btn btn-secondary" onclick="hideCopyModal()" style="margin-top: 20px;">Close</button>
    </div>
  </div>

<script>
const rawText = <?= json_encode($raw) ?>;
let currentContent = '';
let currentType = '';

function generateText(type) {
  const area = document.getElementById('result-area');
  const userEditedText = document.getElementById('raw-text-area').value;
  
  if (!userEditedText.trim()) {
      alert("テキストがありません。");
      return;
  }

  area.innerHTML = '<div style="text-align: center; padding-top: 100px; color: var(--accent-teal);"><i class="fas fa-circle-notch fa-spin fa-3x"></i><p style="margin-top: 20px;">思考資産を構築中...</p></div>';
  
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.dataset.type === type) {
      btn.classList.add('active');
    }
  });
  
  currentType = type;

  fetch('generate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'type=' + encodeURIComponent(type) + '&raw=' + encodeURIComponent(userEditedText)
  })
  .then(res => res.text())
  .then(text => {
    currentContent = text;
    area.innerHTML = text.replace(/\n/g, '<br>');
  })
  .catch(() => {
    area.innerHTML = '<p class="text-warning">Generation failed.</p>';
  });
}

function copyText() {
  const content = document.getElementById('result-area').innerText;
  navigator.clipboard.writeText(content).then(() => {
    showCopyModal();
  });
}

function copyRawText() {
  const text = document.getElementById('raw-text-area').value;
  navigator.clipboard.writeText(text).then(() => {
    showCopyModal();
  });
}

function showCopyModal() {
  const modal = document.getElementById('copyModal');
  modal.classList.add('active');
  setTimeout(hideCopyModal, 2000);
}

function hideCopyModal() {
  const modal = document.getElementById('copyModal');
  modal.classList.remove('active');
}

function createPost() {
  if (!currentContent) {
    alert('Please generate content first.');
    return;
  }
  
  let title = currentContent.substring(0, 30).replace(/\n/g, ' ').trim();
  if (title.length >= 30) {
    title += '...';
  }
  
  sessionStorage.setItem('udatsu_auto_title', title);
  sessionStorage.setItem('udatsu_auto_content', currentContent);
  sessionStorage.setItem('udatsu_auto_fill', '1');
  
  const userEditedText = document.getElementById('raw-text-area').value;
  sessionStorage.setItem('udatsu_auto_raw', userEditedText);
  
  window.location.href = 'mypage/create_post.php?auto_redirect=1';
}
</script>
</body>
</html>