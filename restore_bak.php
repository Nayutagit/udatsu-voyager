<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

// Search all bak files in users/ directory
$dir = __DIR__ . '/users/';
$files = glob($dir . 'YvD0Ab05RJZxSeNEAqAgurEzMEh2*');

$info = [];
foreach ($files as $f) {
    $content = file_get_contents($f);
    $decoded = json_decode($content, true);
    $count = is_array($decoded) ? count($decoded) : 0;
    $info[] = [
        'name' => basename($f),
        'size' => filesize($f),
        'mtime' => date('Y-m-d H:i:s', filemtime($f)),
        'post_count' => $count,
        'sample_titles' => $count > 0 ? array_slice(array_column($decoded, 'title'), 0, 5) : [],
    ];
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
