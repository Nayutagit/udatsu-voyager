<?php
session_start();
if (!isset($_SESSION['uid'])) {
  header("Location: login.php");
  exit();
}

$uid = $_SESSION['uid'];
$userDir = __DIR__ . '/../users/';
$uploadDir = $userDir . $uid . '/posts/';
$postsFile = $userDir . $uid . '_posts.json';

$index = isset($_GET['index']) ? intval($_GET['index']) : -1;
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

if ($index < 0 || !isset($posts[$index])) {
  die("投稿が見つかりません。");
}

$post = $posts[$index];
$wasSharedBefore = !empty($post['is_shared']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');
  $text = htmlspecialchars($_POST['text'] ?? '', ENT_QUOTES, 'UTF-8');
  $date = $_POST['date'] ?? date('Y-m-d');
  $category = htmlspecialchars($_POST['category'] ?? '', ENT_QUOTES, 'UTF-8');

  // 音声ファイル（任意）
  $audioName = $post['audio'] ?? '';
  if (!empty($_FILES['audio']['tmp_name'])) {
    $audioName = uniqid('audio_') . '.' . pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
    move_uploaded_file($_FILES['audio']['tmp_name'], $uploadDir . $audioName);
  }

  // アイキャッチ（任意）
  $thumbnailName = $post['thumbnail'] ?? '';
  if (!empty($_FILES['thumbnail']['tmp_name'])) {
    $thumbnailName = uniqid('thumb_') . '.' . pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
    move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $thumbnailName);
  }

  $isShared = isset($_POST['is_shared']) ? true : false;

  $posts[$index] = [
    'id' => $post['id'] ?? uniqid('job_'),
    'title' => $title,
    'text' => $text,
    'original_text' => $post['original_text'] ?? '',
    'content' => $post['content'] ?? '',
    'date' => $date,
    'category' => $category,
    'audio_file' => $post['audio_file'] ?? '',
    'thumbnail' => $thumbnailName,
    'status' => $post['status'] ?? '下書き',
    'is_shared' => $isShared
  ];

  file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  
  // --- LINE Push Notification to Mutual Followers ---
  if ($isShared && !$wasSharedBefore) {
      $myFollowingFile = $userDir . $uid . '_following.json';
      $myFollowersFile = $userDir . $uid . '_followers.json';
      $lineMapFile = $userDir . 'line_map.json';
      
      $myFollowing = file_exists($myFollowingFile) ? json_decode(file_get_contents($myFollowingFile), true) ?: [] : [];
      $myFollowers = file_exists($myFollowersFile) ? json_decode(file_get_contents($myFollowersFile), true) ?: [] : [];
      $mutualUids = array_intersect($myFollowing, $myFollowers);
      
      if (!empty($mutualUids)) {
          $lineMap = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) ?: [] : [];
          $uidToLineId = array_flip($lineMap); // Map UID => LineUserId
          
          $profileFile = $userDir . $uid . '_profile.json';
          $myProfile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
          $myName = $myProfile['display_name'] ?? 'あなたと相互フォローのユーザー';
          
          $pushMessage = "📢 {$myName}さんが新しい音声をタイムラインに共有しました！\n\nタイトル：{$title}\n\n▼タイムラインを開いて確認する\nhttps://udatsu-voyager.com/mypage/timeline.php?openExternalBrowser=1";
          
          $lineConfig = file_exists(__DIR__ . '/../config/line_config.php') ? require __DIR__ . '/../config/line_config.php' : null;
          if ($lineConfig && !empty($lineConfig['channel_access_token'])) {
              $accessToken = $lineConfig['channel_access_token'];
              
              foreach ($mutualUids as $mutualUid) {
                  if (isset($uidToLineId[$mutualUid])) {
                      $targetLineId = $uidToLineId[$mutualUid];
                      
                      $url = 'https://api.line.me/v2/bot/message/push';
                      $postData = [
                          'to' => $targetLineId,
                          'messages' => [['type' => 'text', 'text' => $pushMessage]]
                      ];
                      
                      $ch = curl_init($url);
                      curl_setopt($ch, CURLOPT_POST, true);
                      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                      curl_setopt($ch, CURLOPT_HTTPHEADER, [
                          'Content-Type: application/json; charset=UTF-8',
                          'Authorization: Bearer ' . $accessToken
                      ]);
                      curl_exec($ch);
                      curl_close($ch);
                  }
              }
          }
      }
  }
  // ------------------------------------------------

  header("Location: dashboard.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>投稿編集 | Udatsu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* Page specific styles */
    .container {
      max-width: 700px;
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
    h1 {
      text-align: center;
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 3rem;
      color: var(--text-white);
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    form {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3rem;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      animation: fadeInUp 1s ease 0.6s both;
      transition: all 0.4s ease;
    }
    form:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    label {
      display: block;
      margin-top: 2rem;
      font-size: 1rem;
      font-weight: 600;
      color: var(--primary-neon);
      margin-bottom: 0.8rem;
    }
    label:first-of-type {
      margin-top: 0;
    }
    input[type="text"],
    input[type="date"],
    select,
    textarea {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      color: var(--text-white);
      transition: all 0.3s ease;
      font-family: 'Inter', 'M PLUS Rounded 1c', sans-serif;
    }
    input[type="text"]:focus,
    input[type="date"]:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary-neon);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.6);
      background: rgba(255, 255, 255, 0.15);
    }
    textarea {
      height: 150px;
      resize: vertical;
      line-height: 1.6;
    }
    select {
      cursor: pointer;
    }
    select option {
      background: #2a2a2e;
      color: var(--text-white);
    }
    input[type="file"] {
      padding: 1rem;
      border: 2px dashed rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.05);
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
      font-family: 'Inter', 'M PLUS Rounded 1c', sans-serif;
    }
    input[type="file"]:hover {
      border-color: var(--primary-neon);
      background: rgba(255, 255, 255, 0.1);
    }
    input[type="submit"] {
      margin-top: 3rem;
      padding: 1.2rem 3rem;
      font-size: 1.1rem;
      background: var(--primary-neon);
      color: #000;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 700;
      transition: all 0.3s ease;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
      width: 100%;
      font-family: 'Inter', 'M PLUS Rounded 1c', sans-serif;
    }
    input[type="submit"]:hover {
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
    }
    .back-button {
      text-align: center;
      margin-top: 3rem;
      animation: fadeInUp 1s ease 0.9s both;
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
        padding: 1rem;
      }
      form {
        padding: 2rem;
      }
      header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
      }
      h1 {
        font-size: 2rem;
      }
      textarea {
        height: 120px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <a href="dashboard.php">
        <img src="../img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
      </a>
      <a href="dashboard.php">Udatsuマイページ</a>
    </header>

    <h1>投稿を編集する</h1>

    <form method="POST" enctype="multipart/form-data">
      <label>タイトル</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>

      <label>本文</label>
      <textarea name="text" required><?php echo htmlspecialchars($post['text']); ?></textarea>

      <label>音声ファイル（差し替え任意）</label>
      <input type="file" name="audio" accept=".m4a,.mp3,.wav">

      <label>アイキャッチ画像（差し替え任意）</label>
      <input type="file" name="thumbnail" accept="image/*">

      <label>収録日</label>
      <input type="date" name="date" value="<?php echo htmlspecialchars($post['date']); ?>" required>

      <label>カテゴリ</label>
      <select name="category" required>
        <option value="">選択してください</option>
        <option value="ボイスジャーナル" <?php if($post['category'] === 'ボイスジャーナル') echo 'selected'; ?>>ボイスジャーナル</option>
        <option value="インタビュー" <?php if($post['category'] === 'インタビュー') echo 'selected'; ?>>インタビュー</option>
        <option value="告知・お知らせ" <?php if($post['category'] === '告知・お知らせ') echo 'selected'; ?>>告知・お知らせ</option>
        <option value="フリートーク" <?php if($post['category'] === 'フリートーク') echo 'selected'; ?>>フリートーク</option>
      </select>

      <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; background: rgba(0,255,204,0.1); padding: 15px; border-radius: 12px; margin-top: 30px;">
        <input type="checkbox" name="is_shared" value="1" <?php echo !empty($post['is_shared']) ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: var(--primary-neon);">
        <div>
          <span style="color: var(--primary-neon); font-size: 1.1rem;"><i class="fas fa-users"></i> 相互フォローに共有する</span><br>
          <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: normal;">チェックを入れると、相互フォローしている相手のタイムラインにこの投稿と音声が表示されます。</span>
        </div>
      </label>

      <input type="submit" value="変更を保存">
    </form>

    <div class="back-button">
      <a href="dashboard.php">← マイページに戻る</a>
    </div>
  </div>
</body>
</html>