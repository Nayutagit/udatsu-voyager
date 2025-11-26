<?php
session_start();

if (empty($_SESSION['is_admin_authenticated'])) {
  header('Location: admin_login.php');
  exit;
}

$userDir = __DIR__ . '/../users/';
$postFiles = glob($userDir . '*_posts.json');
$approvedPosts = [];

foreach ($postFiles as $file) {
  $uid = basename($file, '_posts.json');
  $posts = json_decode(file_get_contents($file), true);
  $profileFile = $userDir . $uid . '_profile.json';
  $profile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
  $displayName = $profile['display_name'] ?? '（名前未設定）';

  foreach ($posts as $index => $post) {
    if (($post['status'] ?? '') === 'Udarow!掲載中') {
      $approvedPosts[] = [
        'uid' => $uid,
        'index' => $index,
        'title' => $post['title'] ?? '（無題）',
        'created' => $post['created'] ?? $post['date'] ?? '',
        'approved' => $post['admin_edit']['edit_time'] ?? '',
        'name' => $displayName,
      ];
    }
  }
}

usort($approvedPosts, function ($a, $b) {
  return strtotime($b['created']) <=> strtotime($a['created']);
});
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udarow!掲載中 投稿一覧 - 管理パネル</title>
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
  <h1>📢 Udarow!掲載中の投稿一覧</h1>

  <table>
    <thead>
      <tr>
        <th>ユーザー名</th>
        <th>タイトル</th>
        <th>申請日時</th>
        <th>承認日時</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($approvedPosts as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= htmlspecialchars($p['title']) ?></td>
          <td><?= htmlspecialchars($p['created']) ?></td>
          <td><?= htmlspecialchars($p['approved']) ?></td>
          <td>
            <a class="link-btn" href="admin_post_detail.php?uid=<?= urlencode($p['uid']) ?>&index=<?= $p['index'] ?>">📖 詳細</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a class="back-link" href="admin_index.php">← 管理パネルに戻る</a>
</body>
</html>