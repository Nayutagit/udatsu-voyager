<?php
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$uid = '3xRUBOkESVQShNx4XWSpsO0w5w13';
$userDir = __DIR__ . '/users/';

echo "<pre>\n";
// Check main file
$main = $userDir . $uid . '_posts.json';
echo "Main file: " . (file_exists($main) ? filesize($main) . ' bytes' : 'NOT FOUND') . "\n";
$content = file_get_contents($main);
echo "Main content (first 200 chars): " . htmlspecialchars(substr($content, 0, 200)) . "\n\n";

// Check backup files
$files = glob($userDir . $uid . '_posts.json.bak*');
echo "Backup files found: " . count($files) . "\n";
foreach ($files as $f) {
    $data = json_decode(file_get_contents($f), true);
    echo "  " . basename($f) . " | " . filesize($f) . " bytes | " . count($data ?? []) . " posts\n";
}

// Check uploads directory for audio files
$uploadDir = __DIR__ . '/uploads/' . $uid . '/';
echo "\nUploads dir exists: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "\n";
if (is_dir($uploadDir)) {
    $audios = glob($uploadDir . '*.m4a') ?: [];
    $audios = array_merge($audios, glob($uploadDir . '*.mp3') ?: []);
    echo "Audio files: " . count($audios) . "\n";
    foreach (array_slice($audios, 0, 5) as $a) {
        echo "  " . basename($a) . "\n";
    }
}
echo "</pre>";
