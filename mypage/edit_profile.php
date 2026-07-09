<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
  header('Location: ../index.php');
  exit();
}

$userDir = __DIR__ . '/../users/';
$profileFile = $userDir . $uid . '_profile.json';

$profile = ["display_name" => $userName ?? 'ゲストさん', "title" => '', "bio" => '', "image" => '', "network_id" => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $displayName = htmlspecialchars($_POST['display_name'] ?? '', ENT_QUOTES, 'UTF-8');
  $title = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');
  $bio = htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES, 'UTF-8');
  $botStyleReference = htmlspecialchars($_POST['bot_style_reference'] ?? '', ENT_QUOTES, 'UTF-8');

  $networkId = trim($_POST['network_id'] ?? '');

  // アイキャッチ（任意）
  $imageName = $profile['image'] ?? '';
  if (!empty($_FILES['image']['tmp_name'])) {
    $uploadDir = __DIR__ . '/../uploads/' . $uid . '/';
    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    $imageName = uniqid('profile_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
  }

  // Network ID validation and mapping
  $networkIdsFile = $userDir . 'network_ids.json';
  $networkIds = file_exists($networkIdsFile) ? json_decode(file_get_contents($networkIdsFile), true) ?: [] : [];
  
  if ($networkId !== '') {
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $networkId)) {
        $error_message = 'Network IDは半角英数字とアンダースコア(_)のみ、3〜20文字で入力してください。';
    } else {
        // Check if taken by someone else
        if (isset($networkIds[$networkId]) && $networkIds[$networkId] !== $uid) {
            $error_message = 'このNetwork IDは既に他のユーザーに使用されています。';
        }
    }
  }

  if (empty($error_message)) {
      // Remove old mapping
      foreach ($networkIds as $key => $mappedUid) {
          if ($mappedUid === $uid) {
              unset($networkIds[$key]);
          }
      }
      
      // Add new mapping if specified
      if ($networkId !== '') {
          $networkIds[$networkId] = $uid;
          $profile['network_id'] = $networkId;
      } else {
          $profile['network_id'] = '';
      }
      
      file_put_contents($networkIdsFile, json_encode($networkIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

      $profile['display_name'] = $displayName;
      $profile['title'] = $title;
      $profile['bio'] = $bio;
      $profile['bot_style_reference'] = $botStyleReference;
      $profile['image'] = $imageName;

      file_put_contents($profileFile, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
      
      header("Location: mypage.php");
      exit();
  }
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>プロフィール編集 | Udatsu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <style>
    .container {
      max-width: 600px;
      margin: 0 auto;
      position: relative;
    }
    form {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3rem;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
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
    textarea {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-white);
      font-family: 'Inter', 'M PLUS Rounded 1c', sans-serif;
    }
    textarea {
      height: 150px;
      resize: vertical;
    }
    input[type="file"] {
      padding: 1rem;
      border: 2px dashed rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      width: 100%;
      color: var(--text-muted);
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
      width: 100%;
    }
    header {
      padding: 1rem 0;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <div class="container" style="padding-top: 50px; padding-bottom: 50px;">
    <header>
      <a href="mypage.php" style="color: var(--text-white); text-decoration: none; font-size: 1.2rem; font-weight: bold;">← Dashboard</a>
    </header>

    <h1 style="text-align: center; margin-bottom: 2rem;">プロフィール編集</h1>

    <?php if ($error_message): ?>
        <div style="background: rgba(244, 67, 54, 0.1); border: 1px solid #f44336; color: #f44336; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <label>Network ID (任意)</label>
      <input type="text" name="network_id" value="<?php echo htmlspecialchars($profile['network_id'] ?? ''); ?>" placeholder="例: nayuta_123" pattern="^[a-zA-Z0-9_]{3,20}$" title="半角英数字とアンダースコアのみ、3〜20文字">
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">友達があなたを検索するときに使う短いIDを設定できます。空欄の場合はシステムID（長めの英数字）が使用されます。</div>

      <label>表示名</label>
      <input type="text" name="display_name" value="<?php echo htmlspecialchars($profile['display_name']); ?>" required>

      <label>肩書き・役職</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($profile['title']); ?>" placeholder="例: 代表取締役CEO">

      <label>自己紹介文 (Bio)</label>
      <textarea name="bio" placeholder="あなたの哲学やスタンスを入力してください"><?php echo htmlspecialchars($profile['bio']); ?></textarea>

      <label>AI分身の話し方手本テキスト (任意)</label>
      <textarea name="bot_style_reference" placeholder="AI分身に模倣させたい話し方や文面のサンプル（例：『承知いたしました。私の見解としては〜〜』などの丁寧な文面、または具体的な話し方の指示）を入力してください" style="height: 120px;"><?php echo htmlspecialchars($profile['bot_style_reference'] ?? ''); ?></textarea>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">このテキストの口調や文面サンプルをAI分身が真似して会話するようになります。</div>

      <label>プロフィール画像（差し替え任意）</label>
      <input type="file" name="image" accept="image/*">

      <input type="submit" value="保存する">
    </form>
  </div>
</body>
</html>
