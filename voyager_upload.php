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
  <link rel="stylesheet" href="css/style.css">
  <style>
    .container {
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }
  </style>
</head>
<body>
<div class="container">
  <!-- Udatsuロゴ -->
  <div class="logo-wrapper" style="text-align: center;">
    <a href="https://udatsuageteko.com/" target="_blank">
      <img src="/img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
    </a>
  </div>

  <!-- ユーザー情報 -->
  <div class="user-info">
    <span><?= htmlspecialchars($userName) ?> さん　現在のプラン：<strong><?= htmlspecialchars($userPlan) ?></strong></span>
    <a href="mypage/dashboard.php" class="mypage-btn">マイページ</a>
  </div>

  <h1>Udatsu Voyager</h1>
  <div class="subtitle">音声をアップして、思考をカタチに。</div>

  <div class="info-box">
    ⏱ アップロード上限：<strong><?= $durationLimit ?>分</strong><br>
    📤 今日の残り回数：<strong><?= $remaining ?> / <?= $uploadLimit ?> 回</strong>
  </div>

  <?php if ($limitReached): ?>
    <div class="info-box" style="color: var(--warning-red); border-color: var(--warning-red); background: rgba(255, 107, 107, 0.1);">
      ⚠️ 今日のアップロード回数が上限に達しました。<br>また明日お試しください。
    </div>
  <?php else: ?>
    <form class="upload-form" action="voyager_upload.php" method="POST" enctype="multipart/form-data">
      <p style="text-align: center; margin-bottom: 1.5rem;">音声ファイルを選んでください（対応形式：m4a, mp3）</p>
      <input type="file" name="audioFile" accept=".m4a,.mp3,.wav" required>
      <button type="submit" style="width: 100%;">送信して解析</button>
    </form>
  <?php endif; ?>

  <p class="footer-note">
    📌 音声は自動で文字起こしされ、整文まで行われます。<br>
    完了後はマイページから記事として編集・保存できます。
  </p>

  <!-- ログアウトボタン -->
  <div class="logout-wrapper" style="text-align: center; margin-top: 3rem;">
    <form method="POST" action="mypage/logout.php">
      <button type="submit" class="logout-link">ログアウト</button>
    </form>
  </div>
</div>

<script>
// ページロード時のアニメーション強化
document.addEventListener('DOMContentLoaded', function() {
  // ロゴクリック時の特別エフェクト
  const logo = document.querySelector('.logo');
  if(logo) {
    logo.addEventListener('click', function() {
      this.style.transition = 'transform 1s ease';
      this.style.transform = 'rotate(360deg)';
      setTimeout(() => {
        this.style.transform = 'rotate(0deg)';
      }, 1000);
    });
  }
  
  // フォーム送信時のローディング効果
  const form = document.querySelector('.upload-form');
  if (form) {
    form.addEventListener('submit', function() {
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.textContent = '解析開始中...';
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.7';
      }
    });
  }
});
</script>
</body>
</html>