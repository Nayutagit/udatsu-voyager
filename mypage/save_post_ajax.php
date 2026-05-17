<?php
/**
 * save_post_ajax.php
 * タイトルと本文のみのAJAX保存（インライン編集用）。
 * ファイルアップロードは edit_post.php で引き続き対応。
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$uid = $_SESSION['uid'] ?? null;
if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$index = $_POST['index'] ?? null;
if (!is_numeric($index)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid index']);
    exit;
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

if (!isset($posts[$index])) {
    echo json_encode(['status' => 'error', 'message' => '投稿が見つかりません']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$text  = trim($_POST['text']  ?? '');
$date  = trim($_POST['date']  ?? '');

if (empty($title)) {
    echo json_encode(['status' => 'error', 'message' => 'タイトルは必須です']);
    exit;
}

$posts[$index]['title'] = $title;
$posts[$index]['text']  = $text;
if (!empty($date)) {
    $posts[$index]['date'] = $date;
}

file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'title' => $title, 'text' => $text, 'date' => $date]);
