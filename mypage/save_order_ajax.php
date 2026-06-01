<?php
/**
 * save_order_ajax.php
 * 自由並び替え時の並び順（IDリスト）を保存する。
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$uid = $_SESSION['uid'] ?? null;
if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$orderJson = $_POST['order'] ?? '';
$newOrder = json_decode($orderJson, true);
if (!is_array($newOrder)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order data']);
    exit;
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

if (!file_exists($postsFile)) {
    echo json_encode(['status' => 'error', 'message' => 'Posts file not found']);
    exit;
}

$posts = json_decode(file_get_contents($postsFile), true) ?: [];

// IDをキーにした一時マッピングを作成
$postMap = [];
foreach ($posts as $post) {
    $pid = $post['id'] ?? '';
    if ($pid !== '') {
        $postMap[$pid] = $post;
    }
}

$orderedPosts = [];
// 1. 指定された並び順に従って配置
foreach ($newOrder as $id) {
    if (isset($postMap[$id])) {
        $orderedPosts[] = $postMap[$id];
        unset($postMap[$id]); // 配置したものはマッピングから削除
    }
}

// 2. 残りの投稿（削除済みや順序に含まれなかったもの）を末尾に保持
foreach ($posts as $originalPost) {
    $origId = $originalPost['id'] ?? '';
    if ($origId === '' || isset($postMap[$origId])) {
        $orderedPosts[] = $originalPost;
    }
}

file_put_contents($postsFile, json_encode($orderedPosts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok']);
