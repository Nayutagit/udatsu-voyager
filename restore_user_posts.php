<?php
/**
 * restore_user_posts.php
 * 一時的なデータ復元スクリプト。使用後は必ず削除すること。
 * 認証キー付き。
 */
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Unauthorized');
}

$uid = $_GET['uid'] ?? '';
if (empty($uid) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $uid)) {
    die('Invalid uid');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid JSON']));
    }
    $path = __DIR__ . '/users/' . $uid . '_posts.json';
    // バックアップ
    if (file_exists($path)) {
        copy($path, $path . '.bak_' . date('YmdHis'));
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => 'ok', 'count' => count($data), 'path' => $path]);
    exit;
}

// GET: 現在のデータ件数を確認
$path = __DIR__ . '/users/' . $uid . '_posts.json';
$current = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
echo json_encode([
    'uid' => $uid,
    'current_count' => count($current ?? []),
    'file_exists' => file_exists($path),
]);
