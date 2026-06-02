<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . PHP_SAPI . "\n";

// 1. Log files
echo "=== LOG FILES ===\n";
$logFiles = [
    'webhook_debug.txt',
    'log/analysis_log.txt',
    'log/cron_retry_log.txt',
    'debug_webhook.log',
];
foreach ($logFiles as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "\n--- $f (last 50 lines) ---\n";
        $lines = file($path);
        echo implode('', array_slice($lines, -50));
    } else {
        echo "\n--- $f: NOT FOUND ---\n";
    }
}

// 2. line_map.json
echo "\n=== LINE MAP ===\n";
$lm = __DIR__ . '/users/line_map.json';
echo file_exists($lm) ? file_get_contents($lm) : "NOT FOUND";

// 3. PHP functions availability
echo "\n\n=== FUNCTIONS ===\n";
echo "shell_exec: " . (function_exists('shell_exec') ? 'OK' : 'DISABLED') . "\n";
echo "exec: " . (function_exists('exec') ? 'OK' : 'DISABLED') . "\n";
echo "proc_open: " . (function_exists('proc_open') ? 'OK' : 'DISABLED') . "\n";
echo "curl: " . (function_exists('curl_init') ? 'OK' : 'DISABLED') . "\n";

// 4. Recent posts
echo "\n=== RECENT POSTS ===\n";
$files = glob(__DIR__ . '/users/*_posts.json');
foreach ($files as $f) {
    $uid = basename($f, '_posts.json');
    $posts = json_decode(file_get_contents($f), true) ?? [];
    if (!empty($posts)) {
        echo "\nUID: $uid\n";
        foreach (array_slice($posts, 0, 3) as $p) {
            echo "  [{$p['status']}] {$p['title']} (id:{$p['id']})\n";
        }
    }
}

echo "\n=== ALL FILES IN USERS/ ===\n";
$all_files = glob(__DIR__ . '/users/*');
foreach ($all_files as $f) {
    echo basename($f) . " (" . filesize($f) . " bytes) - " . date("Y-m-d H:i:s", filemtime($f)) . "\n";
}


echo "</pre>";
