<?php
/**
 * force_retry.php
 * Admin-only: Force re-analysis of posts that have raw titles (新規録音 etc)
 * or are stuck in 解析中/エラー status.
 */
require_once __DIR__ . '/../core/bootstrap.php';

if ($userPlan !== 'admin') {
    http_response_code(403);
    die('Unauthorized');
}

$target_uid = $_GET['uid'] ?? $uid;
$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $target_uid . '_posts.json';

if (!file_exists($postsFile)) {
    die("Posts file not found for uid: " . htmlspecialchars($target_uid));
}

$posts = json_decode(file_get_contents($postsFile), true);
$triggered = [];
$phpPath = getBestPhpCliPath();
$runAnalysis = __DIR__ . '/../run_analysis.php';

foreach ($posts as &$post) {
    $status = $post['status'] ?? '';
    $title = $post['title'] ?? '';
    $audioFile = $post['audio_file'] ?? $post['local_path'] ?? '';
    $jobId = $post['id'] ?? '';

    // Trigger if stuck in 解析中, エラー, or has raw title (新規録音)
    $isRawTitle = preg_match('/^新規録音/', $title) || $title === '🎙';
    $isStuck = in_array($status, ['解析中', 'エラー']);

    if (($isRawTitle || $isStuck) && !empty($audioFile) && !empty($jobId)) {
        $post['status'] = '解析中';
        $cmd = escapeshellarg($phpPath) . ' ' . escapeshellarg($runAnalysis) . ' ' . escapeshellarg($target_uid) . ' ' . escapeshellarg($audioFile) . ' ' . escapeshellarg($jobId) . ' > /dev/null 2>&1 &';
        exec($cmd);
        $triggered[] = ['title' => $title, 'jobId' => $jobId, 'audio' => $audioFile, 'cmd' => $cmd];
    }
}
unset($post);

file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo '<pre style="font-family:monospace; background:#111; color:#0f0; padding:20px;">';
echo "=== Force Retry Results ===\n";
echo "Target UID: $target_uid\n";
echo "Triggered: " . count($triggered) . " jobs\n\n";
foreach ($triggered as $t) {
    echo "✅ " . $t['title'] . " (job: " . $t['jobId'] . ")\n";
    echo "   audio: " . $t['audio'] . "\n";
}
if (empty($triggered)) {
    echo "No posts found that need re-analysis.\n";
    echo "(Posts must have audio_file set and status = 解析中/エラー or title starting with 新規録音)\n";
}
echo '</pre>';
echo '<p><a href="mypage.php" style="color:#fc8; font-family:sans-serif;">← マイページに戻る</a></p>';
