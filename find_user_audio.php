<?php
/**
 * find_user_audio.php v2 - 正しいLINE IDパターン（数字）で探す
 */
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$targetUid = '3xRUBOkESVQShNx4XWSpsO0w5w13'; // 和佳奈さんのUID
$userDir   = __DIR__ . '/users/';
$uploadDir = __DIR__ . '/uploads/';

echo "<pre>\n";
echo "=== 和佳奈さん ({$targetUid}) 音声ファイル特定 ===\n\n";

// Step1: uploads/ の全m4aからユニークなLINE IDを取得（数字形式）
$allAudio = glob($uploadDir . 'line_*_*.m4a') ?: [];
echo "uploads/ 内の m4a ファイル数: " . count($allAudio) . "\n";

$lineIdCounts = [];
foreach ($allAudio as $f) {
    // line_{lineId}_{timestamp}.m4a
    if (preg_match('/^line_(\d+)_\d+\.m4a$/', basename($f), $m)) {
        $lineIdCounts[$m[1]] = ($lineIdCounts[$m[1]] ?? 0) + 1;
    }
}
arsort($lineIdCounts);
echo "ユニークなLINE ID数: " . count($lineIdCounts) . "\n\n";

// Step2: 既存ユーザーのposts.jsonからLINE ID → UID マッピングを作る
$knownMapping = []; // lineId => uid
$allPostFiles = glob($userDir . '*_posts.json') ?: [];
foreach ($allPostFiles as $pf) {
    $uid = preg_replace('/_posts\.json$/', '', basename($pf));
    $posts = json_decode(file_get_contents($pf), true) ?? [];
    foreach ($posts as $p) {
        $af = $p['audio_file'] ?? $p['audio'] ?? '';
        if ($af && preg_match('/line_(\d+)_\d+/', basename($af), $m)) {
            $knownMapping[$m[1]] = $uid;
        }
    }
}

// Step3: ログファイルからUID→LINE IDを探す
$logFile = __DIR__ . '/logs/auth_debug_20260512.log';
$logContent = '';
foreach (glob(__DIR__ . '/logs/*.log') ?: [] as $lf) {
    $c = file_get_contents($lf);
    if (strpos($c, $targetUid) !== false) {
        $logContent .= $c;
    }
}

$lineIdFromLog = null;
if ($logContent) {
    // UID周辺のLINE IDを探す
    preg_match_all('/(\d{15,20})/', $logContent, $matches);
    $candidates = array_unique($matches[1]);
    foreach ($candidates as $candidate) {
        if (isset($lineIdCounts[$candidate])) {
            $lineIdFromLog = $candidate;
            echo "ログからLINE ID候補発見: $candidate\n";
        }
    }
}

// Step4: 未紐づけのLINE IDを表示（= 和佳奈さんの候補）
echo "=== LINE ID別ファイル数（UID紐づけ状況） ===\n";
$unmatched = [];
foreach ($lineIdCounts as $lid => $cnt) {
    $uid = $knownMapping[$lid] ?? null;
    // プロファイル名を取得
    $name = '';
    if ($uid) {
        $pf2 = $userDir . $uid . '_profile.json';
        if (file_exists($pf2)) {
            $pr = json_decode(file_get_contents($pf2), true);
            $name = $pr['display_name'] ?? '';
        }
    }
    $status = $uid ? "→ {$uid} ({$name})" : "→ [未紐づけ]";
    echo "  LINE_{$lid}: {$cnt}件 {$status}\n";
    if (!$uid) $unmatched[$lid] = $cnt;
}

echo "\n=== 未紐づけのLINE ID（和佳奈さんの可能性） ===\n";
foreach ($unmatched as $lid => $cnt) {
    echo "  $lid: {$cnt}ファイル\n";
    // 最初と最後のファイルを表示
    $files = glob($uploadDir . "line_{$lid}_*.m4a") ?: [];
    sort($files);
    if ($files) {
        $first = basename($files[0]);
        $last  = basename(end($files));
        preg_match('/(\d{10})\.m4a$/', $first, $m1);
        preg_match('/(\d{10})\.m4a$/', $last,  $m2);
        $firstDate = isset($m1[1]) ? date('Y-m-d', $m1[1]) : '?';
        $lastDate  = isset($m2[1]) ? date('Y-m-d', $m2[1]) : '?';
        echo "  期間: $firstDate ～ $lastDate\n";
    }
}

echo "\n=== 完了 ===\n";
echo "</pre>";
