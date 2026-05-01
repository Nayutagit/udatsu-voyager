<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
    header("Location: ../index.php");
    exit();
}

$index = $_POST['index'] ?? null;
if (!is_numeric($index)) {
    header("Location: mypage.php");
    exit();
}

$targetUid = $uid;
if ($userPlan === 'admin' && !empty($_POST['target_uid'])) {
    $targetUid = $_POST['target_uid'];
}

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!isset($posts[$index])) {
    header("Location: mypage.php");
    exit();
}
$isRawTitle = preg_match('/^新規録音/', $posts[$index]['title'] ?? '') || ($posts[$index]['title'] ?? '') === '🎙';
if ($posts[$index]['status'] !== 'エラー' && strpos($posts[$index]['title'], '解析エラー') === false && !$isRawTitle && $userPlan !== 'admin') {
    header("Location: mypage.php");
    exit();
}

// Set status to "解析中"
$posts[$index]['status'] = '解析中';
file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Re-run analysis
$audioPath = $posts[$index]['audio_file'] ?? $posts[$index]['audio'] ?? '';
if (!empty($audioPath)) {
    $command = "php " . escapeshellarg(__DIR__ . '/../run_analysis.php') . " " . escapeshellarg($targetUid) . " " . escapeshellarg($audioPath) . " > /dev/null 2>&1 &";
    exec($command);
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo json_encode(['status' => 'success', 'message' => '再試行を開始しました。']);
    exit();
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'mypage.php';
header("Location: " . $referer);
exit();
