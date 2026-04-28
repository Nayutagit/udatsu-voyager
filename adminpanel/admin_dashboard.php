<?php
session_start();

// 🔐 管理者ログイン済みかチェック
if (empty($_SESSION['is_admin_authenticated'])) {
  header('Location: admin_login.php');
  exit;
}

// 🔄 POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = $_POST['uid'] ?? '';
  $index = $_POST['index'] ?? null;
  $action = $_POST['action'] ?? '';
  $postsFile = __DIR__ . '/../users/' . $uid . '_posts.json';
  if (!file_exists($postsFile) || !is_numeric($index)) {
    header('Location: admin_dashboard.php?error=invalid');
    exit;
  }
  $posts = json_decode(file_get_contents($postsFile), true) ?: [];
  if ($action === 'approve_request') {
    $posts[$index]['status'] = 'Udarow!掲載中';
    $posts[$index]['approved_at'] = date('Y-m-d H:i:s');
  } elseif ($action === 'withdraw_approve') {
    $posts[$index]['status'] = '掲載取り下げ済み';
    $posts[$index]['approved_at'] = date('Y-m-d H:i:s');
  } elseif ($action === 'restore_post') {
    $posts[$index]['status'] = 'Udarow!掲載中';
    $posts[$index]['restored_at'] = date('Y-m-d H:i:s');
  }
  file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  header('Location: admin_dashboard.php?success=1');
  exit;
}

// 📁 投稿読み込み
$userDir = __DIR__ . '/../users/';
$postFiles = glob($userDir . '*_posts.json');
$pendingPosts = [];
$withdrawRequests = [];
$withdrawnPosts = [];
$approvedLogs = [];

foreach ($postFiles as $fn) {
  $uid = basename($fn, '_posts.json');
  $posts = json_decode(file_get_contents($fn), true) ?: [];
  $profileFile = $userDir . $uid . '_profile.json';
  $profile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
  $displayName = $profile['display_name'] ?? '（名前未設定）';
  foreach ($posts as $idx => $p) {
    $entry = [
      'uid' => $uid,
      'index' => $idx,
      'name' => $displayName,
      'title' => $p['title'] ?? '（無題）',
      'status' => $p['status'] ?? 'Inbox',
      'created' => $p['created'] ?? $p['date'] ?? '',
      'approved_at' => $p['approved_at'] ?? '',
      'restored_at' => $p['restored_at'] ?? ''
    ];
    if ($entry['status'] === '申請中') {
      $pendingPosts[] = $entry;
    } elseif ($entry['status'] === '取り下げ申請中') {
      $withdrawRequests[] = $entry;
    } elseif ($entry['status'] === '掲載取り下げ済み') {
      $withdrawnPosts[] = $entry;
    } elseif ($entry['status'] === 'Udarow!掲載中' && !empty($entry['approved_at'])) {
      $approvedLogs[] = $entry;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>管理パネル</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;500;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'M PLUS Rounded 1c', 'Meiryo', sans-serif;
      background: #fffef5;
      padding: 20px;
    }
    h1 { font-size: 1.5em; margin-bottom: 1em; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #fff; }
    th, td { border: 1px solid #ccc; padding: 8px; font-size: 0.9em; }
    th { background: #f0f0f0; }
    form button, .link-btn, .button-link {
      background: #fcc800; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer;
      text-decoration: none; color: black; font-size: 0.9em;
    }
    .link-btn { display: inline-block; background: #ccc; margin-left: 5px; }
    .button-link { display: inline-block; margin-bottom: 1em; background: #e0f0ff; color: #004499; border: 1px solid #88b; }
  </style>
</head>
<body>
  <h1>📋 Udatsu 投稿管理ダッシュボード</h1>

  <h2>📝 掲載リクエスト一覧（申請中）</h2>
  <table>
    <thead><tr><th>ユーザー名</th><th>タイトル</th><th>申請日</th><th>操作</th></tr></thead>
    <tbody>
<?php foreach ($pendingPosts as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['title']) ?></td>
    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($p['created']))) ?></td>
    <td>
      <form action="admin_dashboard.php" method="POST" style="display:inline;">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($p['uid']) ?>">
        <input type="hidden" name="index" value="<?= $p['index'] ?>">
        <input type="hidden" name="action" value="approve_request">
        <button type="submit">✔ 掲載承認</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <h2>🚫 掲載取り下げリクエスト一覧</h2>
  <table>
    <thead><tr><th>ユーザー名</th><th>タイトル</th><th>申請日</th><th>操作</th></tr></thead>
    <tbody>
<?php foreach ($withdrawRequests as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['title']) ?></td>
    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($p['created']))) ?></td>
    <td>
      <form action="admin_dashboard.php" method="POST" style="display:inline;">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($p['uid']) ?>">
        <input type="hidden" name="index" value="<?= $p['index'] ?>">
        <input type="hidden" name="action" value="withdraw_approve">
        <button type="submit">🗑 承認</button>
      </form>
      <a href="admin_post_detail.php?uid=<?= $p['uid'] ?>&index=<?= $p['index'] ?>" class="link-btn">📖 詳細</a>
    </td>
  </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <h2>📁 掲載取り下げ済み（承認済）</h2>
  <table>
    <thead><tr><th>ユーザー名</th><th>タイトル</th><th>承認日時</th><th>操作</th></tr></thead>
    <tbody>
<?php foreach ($withdrawnPosts as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['title']) ?></td>
    <td><?= htmlspecialchars($p['approved_at']) ?></td>
    <td>
      <form action="admin_dashboard.php" method="POST" style="display:inline;">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($p['uid']) ?>">
        <input type="hidden" name="index" value="<?= $p['index'] ?>">
        <input type="hidden" name="action" value="restore_post">
        <button type="submit">↩️ 再掲載（復元）</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <h2>📚 対応履歴ログ（承認済投稿）</h2>
  <table>
    <thead><tr><th>ユーザー名</th><th>タイトル</th><th>承認日時</th><th>操作</th></tr></thead>
    <tbody>
<?php foreach ($approvedLogs as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['title']) ?></td>
    <td><?= htmlspecialchars($p['approved_at']) ?></td>
    <td>
      <form action="admin_dashboard.php" method="POST" style="display:inline;">
        <input type="hidden" name="uid" value="<?= htmlspecialchars($p['uid']) ?>">
        <input type="hidden" name="index" value="<?= $p['index'] ?>">
        <input type="hidden" name="action" value="restore_post">
        <button type="submit">↩️ 再掲載</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>