<?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");

$postsFile = __DIR__ . '/../users/GVqz3KfUDqMkhfADbjsyL7yhrXG3_posts.json';

if (!file_exists($postsFile)) {
    echo json_encode([]);
    exit;
}

$raw = file_get_contents($postsFile);
$posts = json_decode($raw, true) ?: [];

// 有効な（削除済でない）投稿のみを抽出
$validPosts = [];
foreach ($posts as $p) {
    $status = $p['status'] ?? '';
    if ($status !== '削除済' && !empty($p['summary'])) {
        $validPosts[] = [
            'id' => $p['id'] ?? uniqid('vj_'),
            'title' => $p['title'] ?? '無題',
            'summary' => $p['summary'] ?? '',
            'text' => $p['text'] ?? '',
            'date' => $p['date'] ?? '',
            'category' => $p['category'] ?? 'ボイスジャーナル',
            'audio' => !empty($p['audio_file']) ? $p['audio_file'] : ($p['audio'] ?? '')
        ];
    }
}

// 日付の降順でソート
usort($validPosts, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

// 最新の3件を取得
$latestPosts = array_slice($validPosts, 0, 3);

echo json_encode($latestPosts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
