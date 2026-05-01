<?php
/**
 * retry_analysis.php
 * exec()を使わず、HTTPリクエスト内で直接解析を実行する（Fire-and-forget対応）
 */
session_start();
$sessionUid  = $_SESSION['uid']      ?? null;
$sessionPlan = $_SESSION['user_plan'] ?? 'guest';

// 権限チェック
if (empty($sessionUid)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$index = $_POST['index'] ?? null;
if (!is_numeric($index)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid index']);
    exit;
}

$targetUid = $sessionUid;
if ($sessionPlan === 'admin' && !empty($_POST['target_uid'])) {
    $targetUid = $_POST['target_uid'];
}

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

if (!isset($posts[$index])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Post not found']);
    exit;
}

// 状態を「解析中」に更新
$posts[$index]['status'] = '解析中';
file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$audioPath = $posts[$index]['audio_file'] ?? $posts[$index]['audio'] ?? $posts[$index]['local_path'] ?? '';
$jobId     = $posts[$index]['id'] ?? '';

// === Fire-and-forget: ブラウザへのレスポンスを先に返して、バックグラウンドで処理を続ける ===
ignore_user_abort(true);
set_time_limit(600); // 長めに設定
ob_start();
echo json_encode(['status' => 'success', 'message' => '解析を開始しました。']);
$size = ob_get_length();
header('Content-Type: application/json');
header('Content-Length: ' . $size);
header('Connection: close');
ob_end_flush();
ob_flush();
flush();

// === ここから解析開始 ===
// run_analysis.php が必要とする値をPOST/GLOBALSにセット
$_POST['uid']       = $targetUid;
$_POST['audioPath'] = $audioPath;
$_POST['jobId']     = $jobId;
$_POST['secret']    = 'voyager_internal_exec_1234';
$_SERVER['REQUEST_METHOD'] = 'POST';

// bootstrap.phpでの上書き対策
$GLOBALS['_preBootstrap_uid'] = $targetUid;

// 解析実行
require_once __DIR__ . '/../run_analysis.php';
