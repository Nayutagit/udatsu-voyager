<?php
session_start();

// 🔐 管理者としてログインしているかチェック
if (empty($_SESSION['is_admin_authenticated'])) {
    http_response_code(403);
    exit('アクセス拒否');
}

// 🔍 POSTデータの取得とバリデーション
$uid   = $_POST['uid'] ?? '';
$index = $_POST['index'] ?? '';

if (!$uid || !is_numeric($index)) {
    header('Location: admin_index.php');
    exit();
}

// 📁 投稿データファイルを読み込む
$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

if (!file_exists($postsFile)) {
    header('Location: admin_index.php');
    exit();
}

$posts = json_decode(file_get_contents($postsFile), true);

// 対象投稿が存在するか確認
if (!isset($posts[$index])) {
    header('Location: admin_index.php');
    exit();
}

// ✅ ステータスを「Udarow!掲載中」に変更
$posts[$index]['status'] = 'Udarow!掲載中';
$posts[$index]['published_at'] = date('Y-m-d H:i:s');

// 💾 ファイルに保存（整形つき）
file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 🔁 管理パネルに戻る
header('Location: admin_index.php');
exit();