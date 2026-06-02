<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'debug123') exit('unauthorized');

$log1 = __DIR__ . '/webhook_debug.txt';
$log2 = __DIR__ . '/log/run_analysis_log.txt';
$log3 = __DIR__ . '/log/analysis_log.txt';

echo "<h1>PHP Info</h1>";
echo "PHP_BINARY: " . PHP_BINARY . "<br>";
echo "phpversion(): " . phpversion() . "<br>";

echo "<h1>Webhook Debug</h1>";
if (file_exists($log1)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($log1)) . "</pre>";
} else {
    echo "<p>Not found</p>";
}

echo "<h1>Run Analysis Log</h1>";
if (file_exists($log2)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($log2)) . "</pre>";
} else {
    echo "<p>Not found</p>";
}

echo "<h1>Analysis Log</h1>";
if (file_exists($log3)) {
    $content = file_get_contents($log3);
    // Limit to last 5000 characters to avoid huge response
    if (strlen($content) > 5000) {
        $content = "--- TRUNCATED (showing last 5000 chars) ---\n" . substr($content, -5000);
    }
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
} else {
    echo "<p>Not found</p>";
}

