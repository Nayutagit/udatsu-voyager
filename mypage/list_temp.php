<?php
header('Content-Type: application/json');
$files = glob(__DIR__ . '/../users/*.json');
$out = [];
foreach ($files as $f) {
    $out[basename($f)] = [
        'size' => filesize($f),
        'mtime' => date('Y-m-d H:i:s', filemtime($f)),
        'preview' => substr(file_get_contents($f), 0, 100)
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
