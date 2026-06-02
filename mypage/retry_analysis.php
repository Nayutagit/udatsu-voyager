<?php
/**
 * retry_analysis.php
 * AJAXから呼ばれ、即座にJSONレスポンスを返してから解析を実行する。
 * fastcgi_finish_request() で接続を閉じてからバックグラウンド処理を継続。
 * exec() / CLI は一切使わない。
 */

// === 依存読み込み（ここでは session/auth のみ） ===
session_start();
$_sessionUid  = $_SESSION['uid']       ?? null;
$_sessionPlan = $_SESSION['user_plan'] ?? 'guest';
session_write_close(); // Unlock session file to prevent layout blocking / page freezing

header('Content-Type: application/json; charset=utf-8');

if (empty($_sessionUid)) {
    echo json_encode(['status' => 'error', 'message' => '未ログインです']);
    exit;
}

$id    = $_POST['id'] ?? '';
$index = $_POST['index'] ?? null;

$targetUid = $_sessionUid;
if ($_sessionPlan === 'admin' && !empty($_POST['target_uid'])) {
    $targetUid = $_POST['target_uid'];
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) {
    $posts = [];
}

if ($id !== '') {
    foreach ($posts as $k => $p) {
        if (($p['id'] ?? '') === $id) {
            $index = $k;
            break;
        }
    }
}

if ($index === null || !isset($posts[$index])) {
    echo json_encode(['status' => 'error', 'message' => '投稿が見つかりません']);
    exit;
}

// ステータスを「解析中」に更新
$posts[$index]['status'] = '解析中';
file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$audioPath = $posts[$index]['audio_file'] ?? $posts[$index]['local_path'] ?? $posts[$index]['audio'] ?? '';
$jobId     = $posts[$index]['id'] ?? '';

// === ブラウザへ即座にレスポンスを返す ===
echo json_encode(['status' => 'success', 'message' => '解析を開始しました。']);

// PHP-FPM専用: レスポンスを送信してブラウザ接続を閉じる
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // フォールバック（Apache等）
    ob_end_flush();
    flush();
}

// === ここから先はバックグラウンド処理（CLI経由で非同期実行） ===
ignore_user_abort(true);

require_once __DIR__ . '/../core/functions.php';
$phpPath = getBestPhpCliPath();
$runAnalysis = __DIR__ . '/../run_analysis.php';
$logFile = __DIR__ . '/../log/analysis_log.txt';
if (!file_exists(__DIR__ . '/../log')) {
    mkdir(__DIR__ . '/../log', 0755, true);
}

$cmd = escapeshellarg($phpPath) . ' '
     . escapeshellarg($runAnalysis) . ' '
     . escapeshellarg($targetUid) . ' '
     . escapeshellarg($audioPath) . ' '
     . escapeshellarg($jobId)
     . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
exec($cmd);
