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

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!isset($posts[$index]) || $posts[$index]['status'] !== 'エラー') {
    header("Location: mypage.php");
    exit();
}

// Set status to "解析中"
$posts[$index]['status'] = '解析中';
file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Re-run analysis
$audioPath = $posts[$index]['audio_file'] ?? '';
if (!empty($audioPath)) {
    $command = "php " . escapeshellarg(__DIR__ . '/../run_analysis.php') . " " . escapeshellarg($uid) . " " . escapeshellarg($audioPath) . " > /dev/null 2>&1 &";
    exec($command);
}

header("Location: mypage.php");
exit();
