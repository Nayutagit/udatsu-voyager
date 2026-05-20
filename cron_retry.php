<?php
/**
 * cron_retry.php
 * Udatsu Voyager - Automated Retry System
 * Scans for failed analysis and retries them.
 * Recommended: Run via Cron every 15-30 minutes.
 */

// Define workspace root
$root = __DIR__;
require_once $root . '/core/bootstrap.php';

$userDir = $root . '/users/';
$postFiles = glob($userDir . '*_posts.json');

$logFile = $root . '/log/cron_retry_log.txt';
if (!file_exists($root . '/log')) mkdir($root . '/log', 0755, true);

file_put_contents($logFile, date('Y-m-d H:i:s') . " - START CRON RETRY\n", FILE_APPEND);

foreach ($postFiles as $file) {
    $uid = basename($file, '_posts.json');
    if ($uid === 'line_map' || $uid === 'line_links') continue;

    $posts = json_decode(file_get_contents($file), true) ?: [];
    $updated = false;

    foreach ($posts as $idx => $post) {
        $status = $post['status'] ?? '';
        $jobId = $post['id'] ?? '';
        $text = $post['text'] ?? '';
        $localPath = $post['local_path'] ?? '';

        $shouldRetry = false;

        // 止まっているステータスをすべてリトライ対象にする
        $retryStatuses = ['エラー', '解析中', 'リトライ中...', 'リトライ中(要約のみ)...', 'リトライ中(音声)...'];
        if (in_array($status, $retryStatuses)) {
            $postsMtime = filemtime($file);
            if (time() - $postsMtime > 300) { // 5分以上更新がなければスタックとみなす
                $shouldRetry = true;
            }
        }

        // 要約失敗のテキストが含まれる場合もリトライ
        if (strpos($text, '(要約解析失敗)') !== false) {
            $shouldRetry = true;
        }

        if ($shouldRetry) {
            // audio_file or local_path
            $audioPath = $post['audio_file'] ?? $post['local_path'] ?? '';
            $isFirebasePath = (strpos($audioPath, 'audio/') === 0);
            if (!empty($audioPath) && (file_exists($root . '/' . $audioPath) || $isFirebasePath)) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Retrying with AUDIO: uid=$uid, jobId=$jobId\n", FILE_APPEND);
                $phpPath = '/usr/bin/php';
                $cmd = escapeshellarg($phpPath) . ' '
                     . escapeshellarg("{$root}/run_analysis.php") . ' '
                     . escapeshellarg($uid) . ' '
                     . escapeshellarg($audioPath) . ' '
                     . escapeshellarg($jobId)
                     . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Executing: $cmd\n", FILE_APPEND);
                exec($cmd);
                $posts[$idx]['status'] = 'リトライ中...';
                $updated = true;
            } elseif (!empty($post['original_text']) || !empty($post['text'])) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Retrying TEXT ONLY: uid=$uid, jobId=$jobId\n", FILE_APPEND);
                $phpPath = '/usr/bin/php';
                $cmd = escapeshellarg($phpPath) . ' '
                     . escapeshellarg("{$root}/run_analysis.php") . ' '
                     . escapeshellarg($uid) . ' '
                     . "'TEXT_ONLY' "
                     . escapeshellarg($jobId)
                     . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Executing: $cmd\n", FILE_APPEND);
                exec($cmd);
                $posts[$idx]['status'] = 'リトライ中(要約のみ)...';
                $updated = true;
            }
        }
    }

    if ($updated) {
        file_put_contents($file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " - END CRON RETRY\n", FILE_APPEND);
echo "Cron retry finished.\n";
