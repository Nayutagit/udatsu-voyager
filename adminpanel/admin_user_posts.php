<?php
session_start();

// 🔐 管理者ログインチェック
if (empty($_SESSION['is_admin_authenticated'])) {
  header('Location: admin_login.php');
  exit;
}

$uid = $_GET['uid'] ?? '';
if (!$uid) {
  echo "ユーザーIDが指定されていません。";
  exit;
}

$userDir = __DIR__ . '/../users/';
$postFile = $userDir . $uid . '_posts.json';
$profileFile = $userDir . $uid . '_profile.json';

if (!file_exists($postFile)) {
  echo "投稿データが見つかりません。";
  exit;
}

$posts = json_decode(file_get_contents($postFile), true);
$profile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
$displayName = $profile['display_name'] ?? '（名前未設定）';
$email = $profile['email'] ?? '（アドレス未設定）';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($displayName) ?>さんの投稿一覧 - 管理パネル</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;500;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'M PLUS Rounded 1c', 'Meiryo', sans-serif;
      background: #fffef5;
      padding: 20px;
    }
    h1 {
      font-size: 1.4em;
      margin-bottom: 1em;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2em;
      background: #fff;
    }
    th, td {
      padding: 8px;
      border: 1px solid #ccc;
      font-size: 0.9em;
    }
    th {
      background: #eee;
    }
    .link-btn {
      color: #0077cc;
      text-decoration: underline;
      font-size: 0.9em;
    }
    .back-link {
      text-decoration: none;
      font-size: 0.9em;
      color: #0077cc;
    }
  </style>
</head>
<body>
  <h1>👤 <?= htmlspecialchars($displayName) ?>さんの投稿一覧</h1>
  <p>📧 <?= htmlspecialchars($email) ?></p>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>タイトル</th>
        <th>ステータス</th>
        <th>投稿日時</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($posts as $i => $p): ?>
      <tr>
        <td><?= $i ?></td>
        <td><?= htmlspecialchars($p['title'] ?? '（無題）') ?></td>
        <td><?= htmlspecialchars($p['status'] ?? '下書き') ?></td>
        <td><?= htmlspecialchars($p['created'] ?? $p['date'] ?? '不明') ?></td>
        <td>
          <a class="link-btn" href="admin_post_detail.php?uid=<?= urlencode($uid) ?>&index=<?= $i ?>">📖 詳細</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <a class="back-link" href="admin_index.php">← 管理パネルに戻る</a>
</body>
</html>