<?php
session_start();

// 🔐 管理者認証チェック
if (empty($_SESSION['is_admin_authenticated'])) {
  header('Location: admin_login.php');
  exit;
}

// 📥 POST データ受け取り
$uid = $_POST['uid'] ?? '';
$index = $_POST['index'] ?? '';
$editedBody = $_POST['edited_body'] ?? '';
$adminNote = $_POST['admin_note'] ?? '';
$udarowStatus = isset($_POST['udarow_status']) ? true : false;
$udarowUrl = $_POST['udarow_url'] ?? '';

if (!$uid || !is_numeric($index)) {
  echo "入力内容が不正です。";
  exit;
}

$userDir = __DIR__ . '/../users/';
$postFile = $userDir . $uid . '_posts.json';

if (!file_exists($postFile)) {
  echo "投稿ファイルが見つかりません。";
  exit;
}

$posts = json_decode(file_get_contents($postFile), true);

if (!isset($posts[$index])) {
  echo "指定された投稿が存在しません。";
  exit;
}

// ✅ ユーザー原文（body/text）は一切変更せず、管理者編集欄だけ保存（admin_edit セクションにまとめる）
$posts[$index]['admin_edit'] = [
  'body' => $editedBody,
  'note' => $adminNote,
  'status' => $udarowStatus,
  'url' => $udarowUrl,
  'edited' => true,
  'edit_time' => date('Y-m-d H:i:s')
];

file_put_contents($postFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ✅ 完了後リダイレクト
header("Location: admin_post_detail.php?uid=$uid&index=$index&saved=1");
exit;