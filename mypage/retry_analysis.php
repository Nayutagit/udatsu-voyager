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

// === ここから先はバックグラウンド処理 ===
ignore_user_abort(true);
set_time_limit(600);

// 依存クラスを読み込む（bootstrap.phpは使わず直接）
$geminiKeyFile = __DIR__ . '/../config/gemini_key.php';
if (file_exists($geminiKeyFile)) require_once $geminiKeyFile;

require_once __DIR__ . '/../core/GeminiService.php';
require_once __DIR__ . '/../core/FirebaseService.php';
require_once __DIR__ . '/../analysis_core.php';

// 解析実行
run_analysis_for_post($targetUid, $audioPath, $jobId);
