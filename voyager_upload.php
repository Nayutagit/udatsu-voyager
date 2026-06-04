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
    $allowedTypes = ['audio/m4a','audio/mp4','audio/x-m4a','audio/mpeg','audio/mp3','audio/x-mp3','audio/wav','audio/x-wav','audio/webm','video/webm','audio/ogg','video/mp4','audio/aac','audio/x-aac','audio/mp4a-latm'];
    if (!in_array($mimeType, $allowedTypes, true)) {
      exit("対応していないファイル形式です。({$mimeType})");
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
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Udatsu Voyager - 音声アップロード</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="manifest" href="manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Voyager">
  <link rel="apple-touch-icon" href="img/udatsu-logo.png">
  <style>
    /* Tab Styles */
    .tab-container {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 30px;
      padding: 5px;
    }
    .tab-btn {
      flex: 1;
      padding: 10px 20px;
      border: none;
      background: none;
      color: var(--text-muted);
      font-weight: bold;
      cursor: pointer;
      border-radius: 25px;
      transition: all 0.3s ease;
    }
    .tab-btn.active {
      background: var(--primary-neon);
      color: #000;
      box-shadow: 0 0 10px rgba(0, 255, 204, 0.5);
    }
    
    /* Upload Zone Fix */
    .upload-zone {
      display: block; /* Ensure it behaves like a block */
      width: 100%;
    }

    /* Recording UI */
    .record-ui {
      display: none;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }
    .record-ui.active {
      display: flex;
    }
    .mic-btn {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: #333;
      border: 2px solid var(--text-muted);
      color: var(--text-muted);
      font-size: 2rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .mic-btn.recording {
      background: #ff4444;
      border-color: #ff4444;
      color: white;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(255, 68, 68, 0.4); }
      70% { box-shadow: 0 0 0 20px rgba(255, 68, 68, 0); }
      100% { box-shadow: 0 0 0 0 rgba(255, 68, 68, 0); }
    }
    .timer {
      font-family: var(--font-mono);
      font-size: 1.5rem;
      color: var(--text-white);
    }
    .visualizer {
      width: 100%;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 3px;
    }
    .bar {
      width: 4px;
      height: 10px;
      background: var(--primary-neon);
      border-radius: 2px;
      transition: height 0.1s ease;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="app-header">
    <a href="mypage/mypage.php" class="app-header__logo" style="text-decoration: none; display: flex; align-items: center;">
      <img src="img/udatsu-logo.png" alt="Udatsu" style="height: 32px; filter: drop-shadow(0 0 5px rgba(252,200,0,0.5));">
    </a>
    <div class="app-header__actions">
      <button class="theme-toggle" id="themeToggleBtn" aria-label="テーマ切替">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>
    </div>
  </header>

  <div class="container" style="padding-top: 120px; padding-bottom: 60px;">
    
    <div class="glass-card animate-fadeup" style="max-width: 800px; margin: 0 auto; text-align: center;">
      <h1 class="text-neon" style="font-size: 2.5rem; margin-bottom: 10px;">Udatsu Voyager</h1>
      <p class="text-muted" style="margin-bottom: 40px;">自分の声から思考を取り出し、マークダウン資産を精製します。</p>

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
        
        <!-- Tabs -->
        <div class="tab-container">
          <button class="tab-btn active" onclick="switchTab('upload')"><i class="fas fa-file-upload"></i> ファイル選択</button>
          <button class="tab-btn" onclick="switchTab('record')"><i class="fas fa-microphone"></i> 直接録音</button>
        </div>

        <form class="upload-form" action="voyager_upload.php" method="POST" enctype="multipart/form-data">
          
          <!-- Upload Tab -->
          <div id="tab-upload" class="tab-content">
            <label for="audioFile" class="upload-zone" id="drop-zone">
              <div class="upload-icon">
                <i class="fas fa-microphone-alt"></i>
              </div>
              <h3 style="margin-bottom: 10px;">Click or Drag Audio File</h3>
              <p class="text-muted" style="font-size: 0.9rem;">Supported: m4a, mp3, wav</p>
              <input type="file" name="audioFile" id="audioFile" accept=".m4a,.mp3,.wav" style="display: none;">
              <div id="file-name" class="text-neon" style="margin-top: 20px; font-weight: 700; min-height: 1.5em;"></div>
            </label>
          </div>

          <!-- Record Tab -->
          <div id="tab-record" class="record-ui">
            <div class="visualizer" id="visualizer">
              <!-- Bars generated by JS -->
            </div>
            <div class="timer" id="timer">00:00</div>
            <button type="button" id="mic-btn" class="mic-btn">
              <i class="fas fa-microphone"></i>
            </button>
            <p class="text-muted" id="record-status">タップして録音開始</p>
            <audio id="audio-preview" controls style="display:none; width: 100%; margin-top: 20px;"></audio>
          </div>

          <button type="submit" class="btn btn-primary" id="submit-btn" style="margin-top: 30px; width: 100%; max-width: 300px;">
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
// Tab Switching
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content, .record-ui').forEach(c => c.style.display = 'none');
  
  if (tab === 'upload') {
    document.querySelector('button[onclick="switchTab(\'upload\')"]').classList.add('active');
    document.getElementById('tab-upload').style.display = 'block';
    document.getElementById('tab-record').classList.remove('active');
  } else {
    document.querySelector('button[onclick="switchTab(\'record\')"]').classList.add('active');
    document.getElementById('tab-upload').style.display = 'none';
    document.getElementById('tab-record').classList.add('active');
    document.getElementById('tab-record').style.display = 'flex';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const dropZone = document.getElementById('drop-zone');
  const fileInput = document.getElementById('audioFile');
  const fileNameDisplay = document.getElementById('file-name');
  const form = document.querySelector('.upload-form');
  const submitBtn = document.getElementById('submit-btn');

  // --- File Upload Logic ---
  if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
    
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', (e) => {
      const files = e.dataTransfer.files;
      fileInput.files = files;
      updateFileName(files[0]);
    }, false);

    fileInput.addEventListener('change', function() {
      updateFileName(this.files[0]);
    });
  }

  function updateFileName(file) {
    if (file) {
      fileNameDisplay.textContent = `Selected: ${file.name}`;
      fileNameDisplay.style.opacity = '1';
    }
  }

  // --- Recording Logic ---
  const micBtn = document.getElementById('mic-btn');
  const timerDisplay = document.getElementById('timer');
  const statusDisplay = document.getElementById('record-status');
  const audioPreview = document.getElementById('audio-preview');
  const visualizer = document.getElementById('visualizer');
  
  // Create visualizer bars
  for(let i=0; i<20; i++) {
    const bar = document.createElement('div');
    bar.className = 'bar';
    visualizer.appendChild(bar);
  }
  const bars = document.querySelectorAll('.bar');

  let mediaRecorder;
  let audioChunks = [];
  let startTime;
  let timerInterval;
  let isRecording = false;

  if (micBtn) {
    micBtn.addEventListener('click', async () => {
      // Clear previous errors
      statusDisplay.style.color = 'var(--text-muted)';
      
      if (!isRecording) {
        // Start Recording
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          
          let options = {};
          // Safely check for supported types
          if (typeof MediaRecorder.isTypeSupported === 'function') {
            if (MediaRecorder.isTypeSupported('audio/mp4')) {
              options = { mimeType: 'audio/mp4' };
            } else if (MediaRecorder.isTypeSupported('audio/webm')) {
              options = { mimeType: 'audio/webm' };
            }
          }

          try {
            mediaRecorder = new MediaRecorder(stream, options);
          } catch (e) {
            console.warn('MediaRecorder init failed with options, trying default', e);
            mediaRecorder = new MediaRecorder(stream);
          }

          audioChunks = [];

          mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
              audioChunks.push(event.data);
            }
          };

          mediaRecorder.onstop = () => {
            try {
              // Determine MIME type
              const mimeType = mediaRecorder.mimeType || 'audio/mp4'; // Fallback to mp4 for iOS
              const audioBlob = new Blob(audioChunks, { type: mimeType });
              const audioUrl = URL.createObjectURL(audioBlob);
              audioPreview.src = audioUrl;
              audioPreview.style.display = 'block';
              
              // Determine extension based on MIME type
              let ext = 'm4a'; // Default to m4a (good for mp4/aac)
              if (mimeType.includes('webm')) ext = 'webm';
              else if (mimeType.includes('wav')) ext = 'wav';
              else if (mimeType.includes('ogg')) ext = 'ogg';
              else if (mimeType.includes('mp3')) ext = 'mp3';

              // Create File object
              const file = new File([audioBlob], `recorded_audio.${ext}`, { type: mimeType });
              const dataTransfer = new DataTransfer();
              dataTransfer.items.add(file);
              fileInput.files = dataTransfer.files;
              
              updateFileName(file);
              statusDisplay.textContent = "録音完了！解析ボタンを押してください";
            } catch (err) {
              statusDisplay.textContent = "処理エラー: " + err.message;
              statusDisplay.style.color = '#ff4444';
            }
          };

          mediaRecorder.start();
          isRecording = true;
          micBtn.classList.add('recording');
          micBtn.innerHTML = '<i class="fas fa-stop"></i>';
          statusDisplay.textContent = "録音中...";
          audioPreview.style.display = 'none';

          // Timer
          startTime = Date.now();
          timerInterval = setInterval(() => {
            const diff = Math.floor((Date.now() - startTime) / 1000);
            const m = Math.floor(diff / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            timerDisplay.textContent = `${m}:${s}`;
            
            // Visualizer animation
            bars.forEach(bar => {
              bar.style.height = Math.random() * 30 + 10 + 'px';
            });
          }, 100);

        } catch (err) {
          console.error(err);
          statusDisplay.textContent = "エラー: " + err.message;
          statusDisplay.style.color = '#ff4444';
          alert("録音を開始できませんでした: " + err.message);
        }
      } else {
        // Stop Recording
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
          mediaRecorder.stop();
        }
        isRecording = false;
        micBtn.classList.remove('recording');
        micBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        clearInterval(timerInterval);
        bars.forEach(bar => bar.style.height = '10px');
      }
    });
  }

  // Loading State
  if (form) {
    form.addEventListener('submit', function(e) {
      if (!fileInput.files.length) {
        e.preventDefault();
        alert("ファイルを選択するか、録音してください。");
        return;
      }
      if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 解析中...';
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.7';
      }
    });
  }
});
</script>

<nav class="app-nav">
  <a href="mypage/mypage.php" class="nav-item">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="mypage/timeline.php" class="nav-item">
    <i class="fas fa-compass"></i>
    <span>探す</span>
  </a>
  <a href="voyager_upload.php" class="nav-item active" aria-label="録音">
    <div class="nav-record">
      <i class="fas fa-microphone"></i>
    </div>
  </a>
  <a href="mypage/network.php" class="nav-item">
    <i class="fas fa-user-friends"></i>
    <span>つながり</span>
  </a>
  <a href="mypage/profile.php" class="nav-item">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>
<script>
// Theme Toggle Script
const savedTheme = localStorage.getItem('udatsu_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);
updateThemeIcon(savedTheme);

function updateThemeIcon(theme) {
  const icon = document.getElementById('themeIcon');
  if (icon) {
    icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
  }
}

document.getElementById('themeToggleBtn').addEventListener('click', function() {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('udatsu_theme', next);
  updateThemeIcon(next);
});
</script>
</body>
</html>