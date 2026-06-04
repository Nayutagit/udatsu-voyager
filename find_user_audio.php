<?php
/**
 * find_user_audio.php - 特定ユーザーの音声ファイルを特定して復元する
 * 使用後は必ず削除すること
 */
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$targetUid = '3xRUBOkESVQShNx4XWSpsO0w5w13'; // 和佳奈さんのUID

$userDir   = __DIR__ . '/users/';
$uploadDir = __DIR__ . '/uploads/';
$logDir    = __DIR__ . '/../log/'; // XServer log dir

echo "<pre>\n";
echo "=== 和佳奈さん（$targetUid）の音声ファイル特定 ===\n\n";

// Step 1: LINE IDの特定
// line_setup.php や LINE連携ファイルを探す
$lineIdFile = $userDir . $targetUid . '_line.json';
$lineId = null;
if (file_exists($lineIdFile)) {
    $lineData = json_decode(file_get_contents($lineIdFile), true);
    $lineId = $lineData['line_uid'] ?? null;
    echo "LINE ID (from _line.json): $lineId\n";
}

// line_users.json を確認
$lineUsersFile = $userDir . 'line_users.json';
if (file_exists($lineUsersFile)) {
    $lineUsers = json_decode(file_get_contents($lineUsersFile), true);
    foreach ($lineUsers as $lid => $uid) {
        if ($uid === $targetUid) {
            $lineId = $lid;
            echo "LINE ID (from line_users.json): $lineId\n";
            break;
        }
    }
}

// 別パスも試す
$lineUsersFile2 = __DIR__ . '/line_users.json';
if (file_exists($lineUsersFile2)) {
    $lineUsers = json_decode(file_get_contents($lineUsersFile2), true);
    foreach ($lineUsers ?? [] as $lid => $uid) {
        if ($uid === $targetUid) {
            $lineId = $lid;
            echo "LINE ID (from root line_users.json): $lineId\n";
            break;
        }
    }
}

if (!$lineId) {
    echo "LINE ID: 直接ファイルから見つからず。ログを確認中...\n";
}

echo "\n";

// Step 2: ログファイルから探す
$logFiles = [];
if (is_dir($logDir)) {
    $logFiles = glob($logDir . '*.log') ?: [];
    $logFiles = array_merge($logFiles, glob($logDir . '*.txt') ?: []);
}
// public_html内のlogも
$logFiles2 = glob(__DIR__ . '/logs/*.log') ?: [];
$logFiles2 = array_merge($logFiles2, glob(__DIR__ . '/debug*.log') ?: []);
$allLogs = array_merge($logFiles, $logFiles2);

echo "ログファイル数: " . count($allLogs) . "\n";
foreach ($allLogs as $lf) {
    echo "  " . basename($lf) . " (" . filesize($lf) . " bytes)\n";
    // UID が含まれているか
    $content = file_get_contents($lf);
    if (strpos($content, $targetUid) !== false) {
        echo "  ★ このファイルにUIDが含まれています\n";
        // LINE IDっぽいパターンを抽出
        preg_match_all('/U[a-f0-9]{32}/', $content, $matches);
        $lineIds = array_unique($matches[0]);
        // UIDの周辺を調べる
        $pos = strpos($content, $targetUid);
        $context = substr($content, max(0, $pos-200), 400);
        foreach ($lineIds as $lid) {
            if (strpos($context, $lid) !== false) {
                echo "  → LINE ID候補: $lid\n";
                $lineId = $lid;
            }
        }
    }
}

echo "\n";

// Step 3: 全ユーザーのposts.jsonからLINE IDを逆引き
echo "=== 他ユーザーのposts.jsonからパターン確認 ===\n";
$allPostsFiles = glob($userDir . '*_posts.json');
$lineIdPattern = [];
foreach ($allPostsFiles as $pf) {
    $uid = preg_replace('/_posts\.json$/', '', basename($pf));
    $posts = json_decode(file_get_contents($pf), true) ?? [];
    foreach ($posts as $p) {
        $af = $p['audio_file'] ?? $p['audio'] ?? '';
        if ($af && preg_match('/line_(U[a-f0-9]{32})_/', $af, $m)) {
            $lineIdPattern[$uid][] = $m[1];
        }
    }
}
$lineIdPattern = array_map(fn($ids) => array_unique($ids), $lineIdPattern);
foreach ($lineIdPattern as $uid => $ids) {
    $name = '';
    $pf2 = $userDir . $uid . '_profile.json';
    if (file_exists($pf2)) {
        $pr = json_decode(file_get_contents($pf2), true);
        $name = $pr['display_name'] ?? '';
    }
    echo "  $uid ($name): LINE_ID=" . implode(', ', $ids) . "\n";
}

echo "\n";

// Step 4: 指定LINE IDが見つかった場合、該当ファイルをリストアップ
if ($lineId) {
    echo "=== LINE ID: $lineId の音声ファイル ===\n";
    $pattern = $uploadDir . 'line_' . $lineId . '_*.m4a';
    $files = glob($pattern) ?: [];
    // サブディレクトリも
    $files2 = glob($uploadDir . '*/' . 'line_' . $lineId . '_*.m4a') ?: [];
    $allFiles = array_merge($files, $files2);
    sort($allFiles);
    echo "該当ファイル数: " . count($allFiles) . "\n\n";
    foreach ($allFiles as $f) {
        $ts = preg_match('/(\d{10,})\.m4a$/', $f, $m) ? date('Y-m-d H:i', $m[1]) : '';
        echo "  " . basename($f) . " | " . round(filesize($f)/1024) . "KB | $ts\n";
    }
} else {
    echo "LINE IDが特定できませんでした。\n";
    echo "uploads/ 内の全ファイル数: " . count(glob($uploadDir . '*.m4a') ?: []) . "\n";
    
    // ユニークなLINE IDを全部出す
    $allAudio = glob($uploadDir . '*.m4a') ?: [];
    $allLineIds = [];
    foreach ($allAudio as $f) {
        if (preg_match('/^line_(U[a-f0-9]{32})_/', basename($f), $m)) {
            $allLineIds[$m[1]] = ($allLineIds[$m[1]] ?? 0) + 1;
        }
    }
    arsort($allLineIds);
    echo "\nアップロード済みLINE IDとファイル数:\n";
    foreach ($allLineIds as $lid => $cnt) {
        // 既知のUIDと照合
        $knownUid = '';
        foreach ($lineIdPattern as $uid => $ids) {
            if (in_array($lid, $ids)) { $knownUid = $uid; break; }
        }
        echo "  $lid: {$cnt}ファイル" . ($knownUid ? " → UID: $knownUid" : " → 未紐づけ") . "\n";
    }
}

echo "\n=== 完了 ===\n";
echo "</pre>";
