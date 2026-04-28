<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
  header('Location: index.php');
  exit();
}

$index = isset($_GET['index']) ? (int)$_GET['index'] : null;
if (!is_numeric($index)) {
  header('Location: my_udastack.php');
  exit();
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

if (!file_exists($postsFile)) {
  header('Location: my_udastack.php');
  exit();
}

$posts = json_decode(file_get_contents($postsFile), true);

if (!isset($posts[$index])) {
  header('Location: my_udastack.php');
  exit();
}

// ステータスをInboxに変更
$posts[$index]['status'] = 'Inbox';

// 保存
file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// リダイレクト
header('Location: my_udastack.php');
exit;