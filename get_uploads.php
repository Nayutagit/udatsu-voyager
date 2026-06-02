<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }
$files = [];
foreach (glob(__DIR__ . '/uploads/*') as $f) {
    if (is_file($f)) {
        $files[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'mtime' => filemtime($f)
        ];
    }
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($files, JSON_PRETTY_PRINT);
