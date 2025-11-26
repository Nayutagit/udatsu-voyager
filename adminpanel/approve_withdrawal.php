<?php
session_start();

// 管理者チェック
if (empty($_SESSION['is_admin_authenticated'])) {
  header("Location: admin_login.php");
  exit;
}

$uid   = $_POST['uid'] ?? '';
$index = $_POST['index'] ?? '';

if (!$uid || !is_numeric($index)) {
  header("Location: admin_dashboard.php");
  exit;
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

if (!file_exists($postsFile)) {
  header("Location: admin_dashboard.php");
  exit;
}

$posts = json_decode(file_get_contents($postsFile), true) ?: [];

if (!isset($posts[$index]) || $posts[$index]['status'] !== '取り下げ申請中') {
  header("Location: admin_dashboard.php");
  exit;
}

// ステータス更新
$posts[$index]['status'] = '掲載取り下げ済み';
$posts[$index]['withdraw_approved_at'] = date('Y-m-d H:i:s');

file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 戻る
header("Location: admin_dashboard.php");
exit;