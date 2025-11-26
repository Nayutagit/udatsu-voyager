<?php
require_once __DIR__ . '/core/bootstrap.php';

if ($userPlan === 'guest') {
  header("Location: index.php");
  exit();
}

require_once __DIR__ . '/plan_limits.php';
$limits = $plan_limits[$userPlan] ?? $plan_limits['trial'];
$durationLimit = floor($limits['duration'] / 60);
$uploadLimit = $limits['daily_uses'];

$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);

$userFile = $uid ? $userDir . $uid . '_upload.json' : null;
$today = date('Y-m-d');
$uploadCount = 0;

if ($userFile && file_exists($userFile)) {
  $data = json_decode(file_get_contents($userFile), true);
  $uploadCount = $data[$today] ?? 0;
}
$remaining = max(0, $uploadLimit - $uploadCount);
$limitReached = ($userPlan !== 'admin') && $uploadCount >= $uploadLimit;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$limitReached) {
  $fileKey = $_FILES['audioFile'] ?? null;

  if ($fileKey && is_uploaded_file($fileKey['tmp_name'])) {
    if ($fileKey['size'] > $maxSizeBytes) {
      session_write_close();
      header("Location: error_file_size.php?plan={$userPlan}&limit={$maxSizeMB}");
      exit();
    }

    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

    $originalName = pathinfo($fileKey['name'], PATHINFO_FILENAME);
    $extension = pathinfo($fileKey['name'], PATHINFO_EXTENSION);
    $safeName = preg_replace("/[^a-zA-Z0-9]/", "_", $originalName);
    $filename = $safeName . "_" . time() . "." . $extension;
    $targetPath = $uploadDir . $filename;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileKey['tmp_name']);
    $allowedTypes = ['audio/m4a','audio/mp4','audio/x-m4a','audio/mpeg','audio/mp3','audio/x-mp3','audio/wav','audio/x-wav','audio/webm'];
    if (!in_array($mimeType, $allowedTypes, true)) {
      exit("対応していないファイル形式です。");
    }

    if (move_uploaded_file($fileKey['tmp_name'], $targetPath)) {
      if ($uid && $userPlan !== 'admin') {
        $data = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];
        $data[$today] = ($data[$today] ?? 0) + 1;
        file_put_contents($userFile, json_encode($data));
      }

      $_SESSION['audio_path'] = $targetPath;
      $_SESSION['original_name'] = $originalName;
      header("Location: processing.php");
      exit();
    } else {
      echo "ファイルの保存に失敗しました。";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu Voyager - 音声アップロード</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
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
        <div class="user-badge">
          <i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName) ?>
        </div>
        <div class="user-badge">
          Plan: <strong><?= htmlspecialchars($userPlan) ?></strong>
        </div>
        <a href="mypage/dashboard.php" class="btn btn-secondary" style="padding: 8px 20px; font-size: 0.9rem;">
          <i class="fas fa-columns"></i> Dashboard
        </a>
      </div>
    </div>
  </header>

  <div class="container" style="padding-top: 120px; padding-bottom: 60px;">
    
    <div class="glass-card animate-fadeup" style="max-width: 800px; margin: 0 auto; text-align: center;">
      <h1 class="text-neon" style="font-size: 2.5rem; margin-bottom: 10px;">Voice Analysis</h1>
      <p class="text-muted" style="margin-bottom: 40px;">音声をアップロードして、思考資産を蓄積しよう。</p>

      <div class="info-box" style="display: flex; justify-content: center; gap: 30px; margin-bottom: 40px; font-family: var(--font-mono); font-size: 0.9rem;">
        <div>
          <span class="text-teal"><i class="fas fa-stopwatch"></i> Limit:</span> 
          <strong><?= $durationLimit ?> min</strong>
        </div>
        <div>
          <span class="text-teal"><i class="fas fa-cloud-upload-alt"></i> Today:</span> 
          <strong><?= $remaining ?> / <?= $uploadLimit ?></strong>
        </div>
      </div>

      <?php if ($limitReached): ?>
        <div style="background: rgba(255, 68, 68, 0.1); border: 1px solid var(--warning-red); padding: 20px; border-radius: 8px; color: var(--warning-red); margin-bottom: 30px;">
          <i class="fas fa-exclamation-triangle"></i> 本日のアップロード上限に達しました。
        </div>
      <?php else: ?>
        <form class="upload-form" action="voyager_upload.php" method="POST" enctype="multipart/form-data">
          
          <label for="audioFile" class="upload-zone" id="drop-zone">
            <div class="upload-icon">
              <i class="fas fa-microphone-alt"></i>
            </div>
            <h3 style="margin-bottom: 10px;">Click or Drag Audio File</h3>
            <p class="text-muted" style="font-size: 0.9rem;">Supported: m4a, mp3, wav</p>
            <input type="file" name="audioFile" id="audioFile" accept=".m4a,.mp3,.wav" required style="display: none;">
            <div id="file-name" class="text-neon" style="margin-top: 20px; font-weight: 700; min-height: 1.5em;"></div>
          </label>

          <button type="submit" class="btn btn-primary" style="margin-top: 30px; width: 100%; max-width: 300px;">
            <i class="fas fa-rocket"></i> 解析を開始する
          </button>
        </form>
      <?php endif; ?>

    </div>

    <div style="text-align: center; margin-top: 40px;">
      <form method="POST" action="mypage/logout.php">
        <button type="submit" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.9rem; transition: 0.3s;">
          <i class="fas fa-sign-out-alt"></i> ログアウト
        </button>
      </form>
    </div>

  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const dropZone = document.getElementById('drop-zone');
  const fileInput = document.getElementById('audioFile');
  const fileNameDisplay = document.getElementById('file-name');
  const form = document.querySelector('.upload-form');

  // Drag & Drop Effects
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
  });

  function highlight(e) {
    dropZone.classList.add('dragover');
  }

  function unhighlight(e) {
    dropZone.classList.remove('dragover');
  }

  dropZone.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
    updateFileName(files[0]);
  }

  fileInput.addEventListener('change', function() {
    updateFileName(this.files[0]);
  });

  function updateFileName(file) {
    if (file) {
      fileNameDisplay.textContent = `Selected: ${file.name}`;
      fileNameDisplay.style.opacity = '1';
    }
  }

  // Loading State
  if (form) {
    form.addEventListener('submit', function() {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 解析中...';
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.7';
      }
    });
  }
});
</script>
</body>
</html>