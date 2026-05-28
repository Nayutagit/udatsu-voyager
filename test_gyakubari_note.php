<?php
/**
 * test_gyakubari_note.php
 * ブラウザから手動でテスト実行するためのトリガー。
 * セキュリティのため簡易的なトークンチェックを行います。
 */

$token = $_GET['token'] ?? '';
if ($token !== 'vj_test_gyakubari_1092') {
    http_response_code(403);
    die("Forbidden: Invalid test token.");
}

echo "<h1>Gyakubari Note Automation Manual Trigger</h1>";
echo "<p>Running generation flow...</p>";
echo "<pre>";

// cronスクリプトをインクルードして実行
// 標準出力をバッファリングして画面にログを表示
ob_start();
require_once __DIR__ . '/cron_gyakubari_note.php';
$output = ob_get_clean();

echo "Execution Output:\n";
echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');

echo "\n\nChecking log file contents:\n";
$logFile = __DIR__ . '/gyakubari_data/automation_log.txt';
if (file_exists($logFile)) {
    $lines = array_slice(explode("\n", file_get_contents($logFile)), -20);
    echo htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8');
} else {
    echo "Log file not found.";
}

echo "</pre>";
echo "<p>Process finished.</p>";
