<?php
/**
 * Udatsu Voyager - VJ Sync API (Anne Special)
 * 特定のUIDの解析済みデータをJSONで返却する
 */
$configFile = __DIR__ . '/config/sync_config.php';
$config = file_exists($configFile) ? require $configFile : null;

if (!$config) {
    http_response_code(500);
    exit('Sync Configuration is missing.');
}

$secret = $_GET['secret'] ?? '';
$uid = $_GET['uid'] ?? '';

if ($secret !== $config['sync_secret'] || $uid !== $config['allowed_uid']) {
    http_response_code(403);
    exit('Unauthorized');
}

$userPostsFile = __DIR__ . '/users/' . $uid . '_posts.json';

if (!file_exists($userPostsFile)) {
    http_response_code(404);
    exit('No data found for this user.');
}

header('Content-Type: application/json; charset=UTF-8');
echo file_get_contents($userPostsFile);
