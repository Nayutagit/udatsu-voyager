<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$logFile = __DIR__ . '/log/analysis_log.txt';
if (!file_exists($logFile)) {
    exit("Log file not found");
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$filtered = [];
foreach ($lines as $line) {
    if (strpos($line, '[run_analysis_for_post]') !== false || strpos($line, 'DONE:') !== false || strpos($line, 'ERROR:') !== false) {
        $filtered[] = $line;
    }
}

header('Content-Type: text/plain; charset=UTF-8');
echo implode("\n", $filtered);

