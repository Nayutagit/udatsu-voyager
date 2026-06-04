<?php
/**
 * rebuild_wakana_posts.php
 * 和佳奈さん（3xRUBOkESVQShNx4XWSpsO0w5w13）の投稿をwebhookログから再構築する
 */
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$targetUid   = '3xRUBOkESVQShNx4XWSpsO0w5w13';
$lineUserId  = 'Uda59073737d03192c9c1a1fc04161ed9';
$userDir     = __DIR__ . '/users/';
$uploadDir   = __DIR__ . '/uploads/';
$postsFile   = $userDir . $targetUid . '_posts.json';
$debugFile   = __DIR__ . '/webhook_debug.txt';

echo "<pre>\n";
echo "=== 和佳奈さん投稿復元スクリプト ===\n\n";

// Step1: webhookログからこのユーザーが送ったファイルの一覧を抽出
$lines = file($debugFile, FILE_IGNORE_NEW_LINES) ?? [];
$recoveredFiles = [];

foreach ($lines as $i => $line) {
    // "Processing event" ラインで userId が一致するものを見つける
    if (strpos($line, "userId=$lineUserId") !== false && strpos($line, 'messageType=file') !== false) {
        // 対応するPayloadラインを探す（前後の行にある）
        for ($j = max(0, $i-5); $j <= min(count($lines)-1, $i+5); $j++) {
            if (strpos($lines[$j], '"fileName"') !== false) {
                // JSON payloadから情報を抽出
                if (preg_match('/"id":"(\d+)"/', $lines[$j], $mId) &&
                    preg_match('/"fileName":"([^"]+)"/', $lines[$j], $mName) &&
                    preg_match('/"fileSize":(\d+)/', $lines[$j], $mSize) &&
                    preg_match('/"timestamp":(\d+)/', $lines[$j], $mTs)) {
                    
                    $messageId = $mId[1];
                    $fileName  = $mName[1];
                    $fileSize  = (int)$mSize[1];
                    $timestamp = (int)$mTs[1] / 1000; // ms → sec
                    
                    // 対応するuploadsファイルを探す（messageIdで）
                    $audioFiles = glob($uploadDir . "line_{$messageId}_*.m4a") ?: [];
                    $audioFiles = array_merge($audioFiles, glob($uploadDir . "line_{$messageId}_*") ?: []);
                    
                    $recoveredFiles[$messageId] = [
                        'messageId' => $messageId,
                        'fileName'  => $fileName,
                        'fileSize'  => $fileSize,
                        'timestamp' => $timestamp,
                        'date'      => date('Y-m-d', $timestamp),
                        'audioPath' => !empty($audioFiles) ? 'uploads/' . basename($audioFiles[0]) : null,
                        'fileExists'=> !empty($audioFiles),
                    ];
                    break;
                }
            }
        }
    }
}

// 重複排除してソート
uasort($recoveredFiles, fn($a, $b) => $a['timestamp'] - $b['timestamp']);

echo "webhookログから見つかったファイル数: " . count($recoveredFiles) . "\n";
$existing = array_filter($recoveredFiles, fn($f) => $f['fileExists']);
$missing  = array_filter($recoveredFiles, fn($f) => !$f['fileExists']);
echo "  → uploadsに存在する: " . count($existing) . "件\n";
echo "  → 見つからない: " . count($missing) . "件\n\n";

if (isset($_GET['action']) && $_GET['action'] === 'preview') {
    echo "=== 復元対象ファイル一覧（プレビュー） ===\n";
    foreach ($recoveredFiles as $f) {
        $exist = $f['fileExists'] ? '✓' : '✗';
        echo "  {$exist} {$f['date']} | {$f['fileName']} | " . round($f['fileSize']/1024/1024, 1) . "MB | audio: " . ($f['audioPath'] ?? 'なし') . "\n";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'rebuild') {
    echo "=== posts.jsonを再構築します ===\n";
    
    // 既存のposts.jsonをバックアップ
    if (file_exists($postsFile)) {
        copy($postsFile, $postsFile . '.bak_' . date('YmdHis'));
    }
    
    $newPosts = [];
    foreach ($existing as $f) {
        $ext      = pathinfo($f['fileName'], PATHINFO_EXTENSION) ?: 'm4a';
        $newPosts[] = [
            'id'          => 'recovered_' . $f['messageId'],
            'title'       => pathinfo($f['fileName'], PATHINFO_FILENAME),
            'date'        => $f['date'],
            'text'        => '',
            'summary'     => '',
            'audio_file'  => $f['audioPath'],
            'status'      => '解析待ち',
            'created_at'  => date('Y-m-d H:i:s', $f['timestamp']),
        ];
    }
    
    // 新しい順に並べ替え
    usort($newPosts, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    
    file_put_contents($postsFile, json_encode($newPosts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "復元完了！ " . count($newPosts) . "件のレコードをposts.jsonに書き込みました\n";
    echo "次のステップ: 各投稿を再解析してテキスト化が必要です\n";
}

echo "\n";
echo "操作:\n";
echo "  プレビュー: ?key=...&action=preview\n";
echo "  再構築実行: ?key=...&action=rebuild\n";
echo "</pre>";
