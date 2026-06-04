<?php
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$lineMapFile = __DIR__ . '/users/line_map.json';
$userDir = __DIR__ . '/users/';

echo "<pre>\n";
echo "=== line_map.json ===\n";
if (file_exists($lineMapFile)) {
    $lineMap = json_decode(file_get_contents($lineMapFile), true) ?? [];
    echo "マッピング数: " . count($lineMap) . "\n\n";
    foreach ($lineMap as $lineId => $uid) {
        $pf = $userDir . $uid . '_profile.json';
        $name = '';
        if (file_exists($pf)) {
            $pr = json_decode(file_get_contents($pf), true);
            $name = $pr['display_name'] ?? '';
            $email = $pr['email'] ?? '';
        }
        echo "LINE: $lineId\n  → UID: $uid ($name / $email)\n\n";
    }
} else {
    echo "ファイルが存在しません: $lineMapFile\n";
}

// webhook_debug.txt からも探す
echo "\n=== webhook_debug.txt の uid=3xRUBOkESVQShNx4XWSpsO0w5w13 の行 ===\n";
$debugFile = __DIR__ . '/webhook_debug.txt';
if (file_exists($debugFile)) {
    $lines = file($debugFile);
    foreach ($lines as $i => $line) {
        if (strpos($line, '3xRUBOkESVQShNx4XWSpsO0w5w13') !== false) {
            // 前後の行も表示
            echo "Line $i: " . trim($line) . "\n";
            if (isset($lines[$i-1])) echo "  Before: " . trim($lines[$i-1]) . "\n";
            if (isset($lines[$i+1])) echo "  After: " . trim($lines[$i+1]) . "\n";
        }
    }
    // userId が含まれる行から和佳奈さんのLINE IDを探す
    echo "\n=== 全ユニークなLINE userId ===\n";
    $userIds = [];
    foreach ($lines as $line) {
        if (preg_match('/"userId":"(U[a-f0-9]+)"/', $line, $m)) {
            $userIds[$m[1]] = ($userIds[$m[1]] ?? 0) + 1;
        }
        if (preg_match('/userId=([^\s,]+)/', $line, $m)) {
            $userIds[$m[1]] = ($userIds[$m[1]] ?? 0) + 1;
        }
    }
    arsort($userIds);
    foreach ($userIds as $uid => $cnt) {
        $mappedUid = $lineMap[$uid] ?? '未紐づけ';
        echo "  $uid: {$cnt}回 → $mappedUid\n";
    }
} else {
    echo "webhook_debug.txt が存在しません\n";
}
echo "</pre>";
