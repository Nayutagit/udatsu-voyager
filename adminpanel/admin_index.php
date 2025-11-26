<?php
session_start();
if (empty($_SESSION['is_admin_authenticated'])) {
  header("Location: admin_login.php");
  exit;
}

// データの読み込み
$userDir = __DIR__ . '/../users/';
$postFiles = glob($userDir . '*_posts.json');

$now = date('Y-m-d H:i');
$today = date('Y-m-d');

$pendingCount = 0;
$withdrawCount = 0;
$totalUsers = 0;
$totalPosts = 0;
$recentPosts = [];

foreach ($postFiles as $fn) {
  $totalUsers++;
  $uid = basename($fn, '_posts.json');
  $posts = json_decode(file_get_contents($fn), true) ?: [];
  $totalPosts += count($posts);

  foreach ($posts as $p) {
    $status = $p['status'] ?? '';
    $date = $p['created'] ?? $p['date'] ?? '';
    if ($status === '申請中') $pendingCount++;
    if ($status === '取り下げ申請中') $withdrawCount++;
    if (strpos($date, $today) === 0) {
      $recentPosts[] = [
        'title' => $p['title'] ?? '（無題）',
        'status' => $status,
        'date' => $date,
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu 管理パネル トップ</title>
  <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'M PLUS Rounded 1c', sans-serif;
      background: #fefbe0;
      padding: 2em;
      text-align: center;
    }
    h1 {
      font-size: 1.6em;
      margin-bottom: 1em;
    }
    .summary-box {
      background: #fff;
      padding: 1em;
      border-radius: 10px;
      margin: 1em auto 2em;
      width: 90%;
      max-width: 600px;
      box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }
    .summary-box h2 {
      font-size: 1.2em;
      margin-bottom: 0.5em;
    }
    .summary-item {
      font-size: 0.95em;
      margin: 0.3em 0;
    }
    .nav-buttons a,
    .logout-btn {
      display: inline-block;
      margin: 0.5em;
      padding: 0.6em 1.4em;
      background: #006888;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      font-size: 1em;
    }
    .logout-btn {
      background: #444;
      border: none;
      cursor: pointer;
    }
    .logout-btn:hover {
      background: #222;
    }
    .recent-list {
      text-align: left;
      padding-left: 2em;
      font-size: 0.95em;
    }
    .recent-list li {
      margin: 0.3em 0;
    }
  </style>
</head>
<body>
  <h1>📊 Udatsu 管理パネル</h1>

  <div class="summary-box">
    <h2>🧾 現在のステータス</h2>
    <div class="summary-item">⏱ 最終更新：<?= htmlspecialchars($now) ?></div>
    <div class="summary-item">👥 登録ユーザー数：<?= $totalUsers ?></div>
    <div class="summary-item">📝 総投稿数：<?= $totalPosts ?></div>
    <div class="summary-item">📤 掲載リクエスト（申請中）：<strong><?= $pendingCount ?></strong></div>
    <div class="summary-item">🚫 取り下げリクエスト：<strong><?= $withdrawCount ?></strong></div>
  </div>

  <?php if ($recentPosts): ?>
    <div class="summary-box">
      <h2>📅 本日の投稿</h2>
      <ul class="recent-list">
        <?php foreach ($recentPosts as $p): ?>
          <li><?= htmlspecialchars($p['date']) ?> ｜ <?= htmlspecialchars($p['title']) ?>（<?= htmlspecialchars($p['status']) ?>）</li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="nav-buttons">
    <a href="admin_dashboard.php">📄 投稿ダッシュボード</a>
    <a href="admin_approved_posts.php">📌 掲載中の投稿</a>
    <a href="admin_user_posts.php">👤 ユーザー別投稿一覧</a>
  </div>

  <form method="post" action="admin_logout.php">
    <button type="submit" class="logout-btn">🚪 ログアウト</button>
  </form>
</body>
</html>