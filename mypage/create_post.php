<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
  header("Location: login.php");
  exit();
}

$userDir     = __DIR__ . '/../users/';
$uploadDir   = $userDir . $uid . '/posts/';
$postsFile   = $userDir . $uid . '_posts.json';

if (!file_exists($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$currentMonth = date('Y-m');
$count = 0;
foreach ($posts as $p) {
  if (
    isset($p['status'], $p['date']) &&
    in_array($p['status'], ['下書き', '申請中', '公開済'], true) &&
    strpos($p['date'], $currentMonth) === 0
  ) {
    $count++;
  }
}

$limit = (int)($planLimits['post_limit'] ?? 0);

// URLパラメータから自動リダイレクトフラグを取得
$autoRedirect = $_GET['auto_redirect'] ?? '';

// 実際の投稿データかどうかをチェック
$isRealSubmission = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && isset($_POST['text']);

if ($isRealSubmission) {
  if ($count >= $limit) {
    echo "<script>alert('このプランでは今月の投稿保存上限に達しています。上位プランをご検討ください。'); window.location.href = 'dashboard.php';</script>";
    exit();
  }

  $title     = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');
  $text      = htmlspecialchars($_POST['text'] ?? '', ENT_QUOTES, 'UTF-8');
  $rawText   = htmlspecialchars($_POST['raw_text'] ?? '', ENT_QUOTES, 'UTF-8');
  $date      = $_POST['date'] ?? date('Y-m-d');
  $category  = htmlspecialchars($_POST['category'] ?? '', ENT_QUOTES, 'UTF-8');
  $status    = '下書き';

  $audioName = '';
  if (!empty($_FILES['audio']['tmp_name'])) {
    $audioName = uniqid('audio_') . '.' . pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
    move_uploaded_file($_FILES['audio']['tmp_name'], $uploadDir . $audioName);
  }

  $thumbName = '';
  if (!empty($_FILES['thumbnail']['tmp_name'])) {
    $thumbName = uniqid('thumb_') . '.' . pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
    move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbName);
  }

  $newPost = [
    'title'        => $title,
    'text'         => $text,
    'original_text'=> $rawText ? $rawText : $text,
    'content'      => $text,
    'date'         => $date,
    'category'     => $category,
    'audio'        => $audioName,
    'thumbnail'    => $thumbName,
    'status'       => $status
  ];

  $posts[] = $newPost;
  file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  header("Location: dashboard.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>新規投稿作成 | Udatsu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* Page specific styles */
    .container {
      max-width: 800px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
      padding: 2rem;
    }
    header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2rem;
      padding: 1.5rem 2rem;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      animation: slideDown 1s ease;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    header img.logo {
      height: 48px;
      filter: drop-shadow(0 0 10px var(--neon-gold-glow));
      transition: all 0.3s ease;
    }
    header img.logo:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 0 20px var(--neon-gold-glow));
    }
    header a {
      text-decoration: none;
      font-weight: 700;
      font-size: 1.2rem;
      color: var(--text-white);
      transition: all 0.3s ease;
    }
    header a:hover {
      color: var(--primary-neon);
      text-shadow: 0 0 10px var(--neon-gold-glow);
    }
    h1 {
      font-size: 2.2rem;
      font-weight: 700;
      text-align: center;
      color: var(--text-white);
      text-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
      margin-bottom: 2rem;
      animation: fadeInUp 1s ease 0.2s both;
    }
    .warning {
      border: 2px solid #f44336;
      background: rgba(244, 67, 54, 0.1);
      backdrop-filter: blur(20px);
      color: #f44336;
      padding: 2rem;
      border-radius: 4px;
      max-width: 100%;
      margin: 2rem auto;
      font-size: 1rem;
      text-align: center;
      box-shadow: 0 0 20px rgba(244, 67, 54, 0.3);
      animation: fadeInUp 1s ease 0.4s both;
    }
    .warning a {
      display: inline-block;
      margin-top: 1rem;
      padding: 0.8rem 1.5rem;
      background: var(--primary-neon);
      color: #000;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 700;
      transition: all 0.3s ease;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
    .warning a:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
    }
    .description {
      font-size: 1rem;
      color: var(--text-muted);
      text-align: center;
      margin-bottom: 2rem;
      line-height: 1.6;
      animation: fadeInUp 1s ease 0.6s both;
    }
    .description a {
      color: var(--primary-neon);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .description a:hover {
      text-shadow: 0 0 10px var(--neon-gold-glow);
    }
    .voyager-link {
      text-align: center;
      margin-bottom: 3rem;
      animation: fadeInUp 1s ease 0.8s both;
    }
    .voyager-link a {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      padding: 1rem 2rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 700;
      font-size: 1rem;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
      display: inline-block;
    }
    .voyager-link a:hover {
      background: var(--primary-neon);
      color: #000;
      transform: translateY(-3px);
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
      border-color: var(--primary-neon);
    }
    form {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin: 0 auto;
      animation: fadeInUp 1s ease 1s both;
      transition: all 0.3s ease;
    }
    form:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border-color: var(--primary-neon);
    }
    label {
      display: block;
      margin-top: 2rem;
      font-weight: 600;
      font-size: 1rem;
      color: var(--text-white);
      margin-bottom: 0.5rem;
    }
    label:first-of-type {
      margin-top: 0;
    }
    input[type="text"],
    textarea,
    input[type="date"],
    select,
    input[type="file"] {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background: rgba(0, 0, 0, 0.3);
      color: var(--text-white);
      font-family: inherit;
      transition: all 0.3s ease;
    }
    textarea {
      height: 150px;
      resize: vertical;
      line-height: 1.6;
    }
    input[type="text"]:focus,
    textarea:focus,
    input[type="date"]:focus,
    select:focus {
      outline: none;
      border-color: var(--primary-neon);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);
    }
    input[type="file"] {
      background: rgba(255, 255, 255, 0.05);
      color: var(--text-white);
      border: 2px dashed rgba(255, 255, 255, 0.2);
      cursor: pointer;
    }
    input[type="file"]:hover {
      border-color: var(--primary-neon);
      background: rgba(252, 200, 0, 0.05);
    }
    .preview {
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-top: 0.5rem;
      font-style: italic;
    }
    .action-buttons {
      display: flex;
      gap: 1.5rem;
      margin-top: 3rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    .action-buttons input[type="submit"],
    .action-buttons button {
      font-size: 1.1rem;
      padding: 1rem 2.5rem;
      border-radius: 50px;
      border: none;
      cursor: pointer;
      font-weight: 700;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    .action-buttons input[type="submit"] {
      background: var(--primary-neon);
      color: #000;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
    }
    .action-buttons input[type="submit"]:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
    }
    .action-buttons .cancel-btn {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-muted);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
    }
    .action-buttons .cancel-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      color: var(--text-white);
      transform: translateY(-2px);
    }
    .back-button {
      text-align: center;
      margin-top: 3rem;
      animation: fadeInUp 1s ease 1.2s both;
    }
    .back-button a {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-muted);
      padding: 1rem 2rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
      display: inline-block;
    }
    .back-button a:hover {
      color: var(--text-white);
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
      }
      h1 {
        font-size: 1.8rem;
      }
      form {
        padding: 2rem;
      }
      .action-buttons {
        flex-direction: column;
        align-items: center;
      }
      .action-buttons input[type="submit"],
      .action-buttons button {
        width: 100%;
        max-width: 300px;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <header>
    <a href="dashboard.php">
      <img src="../img/udatsu-logo.png" class="logo" alt="Udatsuロゴ">
    </a>
    <a href="dashboard.php">Udatsuマイページ</a>
  </header>

  <h1>新規投稿作成</h1>

  <?php if ($count >= $limit): ?>
    <div class="warning">
      今月の記事作成上限に達しています！<br>
      <small>さらにうだつを上げるため、アップグレードをご検討ください。</small><br>
      <a href="https://udatsu-voyager.com/mypage/membership.php">メンバーシップを確認する</a>
    </div>
  <?php endif; ?>

  <div class="description">
    投稿は日記としても残せます。<br>
    <strong>ライトプラン以上</strong>の方は <a href="my_udastack.php">My Udastack</a> に思考資産として積み上げることができます。
  </div>
  
  <div class="voyager-link">
    <a href="https://udatsu-voyager.com/voyager_upload.php">Udatsu Voyagerを使って音声で記事作成する</a>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="raw_text" id="rawTextInput">
    <label>タイトル（必須）</label>
    <input type="text" name="title" id="titleInput" required>

    <label>本文（必須）</label>
    <textarea name="text" id="contentTextarea" required></textarea>

    <label>音声ファイル（任意）</label>
    <input type="file" name="audio" accept="audio/*">
    <div class="preview" id="audio-preview"></div>

    <label>アイキャッチ画像（任意）</label>
    <input type="file" name="thumbnail" accept="image/*">
    <div class="preview" id="thumbnail-preview"></div>

    <label>作成日（必須）</label>
    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

    <label>カテゴリ（必須）</label>
    <select name="category" required>
      <option value="">選択してください</option>
      <option value="ボイスジャーナリング">ボイスジャーナリング</option>
      <option value="ビジネス">ビジネス</option>
      <option value="プライベート">プライベート</option>
      <option value="告知・お知らせ">告知・お知らせ</option>
      <option value="フリートーク">フリートーク</option>
      <option value="ブログ">ブログ</option>
      <option value="note">note</option>
    </select>

    <div class="action-buttons">
      <input type="submit" value="保存">
      <button type="button" class="cancel-btn" onclick="window.location.href='dashboard.php'">キャンセル</button>
    </div>
  </form>

  <div class="back-button">
    <a href="dashboard.php">← マイページに戻る</a>
  </div>
</div>

<script>
// セッションストレージから自動入力データを読み込み
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const autoRedirect = urlParams.get('auto_redirect');
  
  if (autoRedirect === '1') {
    // セッションストレージから読み込み
    const autoTitle = sessionStorage.getItem('udatsu_auto_title');
    const autoContent = sessionStorage.getItem('udatsu_auto_content');
    const autoFill = sessionStorage.getItem('udatsu_auto_fill');
    const autoRaw = sessionStorage.getItem('udatsu_auto_raw');
    
    if (autoTitle && autoContent && autoFill === '1') {
      const titleInput = document.getElementById('titleInput');
      const contentTextarea = document.getElementById('contentTextarea');
      const rawInput = document.getElementById('rawTextInput');
      
      titleInput.value = autoTitle;
      contentTextarea.value = autoContent;
      if (autoRaw) rawInput.value = autoRaw;
      
      // ハイライト効果
      titleInput.style.borderColor = 'var(--primary-neon)';
      titleInput.style.boxShadow = '0 0 15px rgba(252, 200, 0, 0.6)';
      contentTextarea.style.borderColor = 'var(--primary-neon)';
      contentTextarea.style.boxShadow = '0 0 15px rgba(252, 200, 0, 0.6)';
      
      // 3秒後にハイライトを元に戻す
      setTimeout(() => {
        titleInput.style.borderColor = '';
        titleInput.style.boxShadow = '';
        contentTextarea.style.borderColor = '';
        contentTextarea.style.boxShadow = '';
      }, 3000);
      
      // セッションストレージをクリア
      sessionStorage.removeItem('udatsu_auto_title');
      sessionStorage.removeItem('udatsu_auto_content');
      sessionStorage.removeItem('udatsu_auto_fill');
      sessionStorage.removeItem('udatsu_auto_raw');
    }
  }
});

// ファイルプレビュー機能
document.querySelector('input[name="audio"]').addEventListener('change', function(e) {
  const preview = document.getElementById('audio-preview');
  if (e.target.files.length > 0) {
    preview.textContent = `選択されたファイル: ${e.target.files[0].name}`;
  } else {
    preview.textContent = '';
  }
});

document.querySelector('input[name="thumbnail"]').addEventListener('change', function(e) {
  const preview = document.getElementById('thumbnail-preview');
  if (e.target.files.length > 0) {
    preview.textContent = `選択されたファイル: ${e.target.files[0].name}`;
  } else {
    preview.textContent = '';
  }
});
</script>
</body>
</html>