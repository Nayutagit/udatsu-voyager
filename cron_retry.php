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

        // 1. Explicit errors
        if ($status === 'エラー') {
            $shouldRetry = true;
        } 
        // 2. Summary failures
        elseif (strpos($text, '(要約解析失敗)') !== false) {
            $shouldRetry = true;
        }
        // 3. Stale "Processing" (more than 30 mins)
        elseif ($status === '解析中') {
            if (preg_match('/job_(\d+)/', $jobId, $matches)) {
                $jobTime = (int)$matches[1];
                if (time() - $jobTime > 1800) { // 30 mins
                    $shouldRetry = true;
                }
            }
        }

        if ($shouldRetry) {
            if (!empty($localPath) && file_exists($root . '/' . $localPath)) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Retrying with AUDIO: uid=$uid, jobId=$jobId\n", FILE_APPEND);
                $phpPath = PHP_BINARY ?: 'php';
                $cmd = "{$phpPath} \"{$root}/run_analysis.php\" \"{$uid}\" \"{$localPath}\" \"{$jobId}\" > /dev/null 2>&1 &";
                exec($cmd);
                $posts[$idx]['status'] = 'リトライ中(音声)...';
                $updated = true;
            } elseif (!empty($post['original_text']) || !empty($post['text'])) {
                // Audio missing but we have text? Let's retry just the summarization/refinement
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Retrying with TEXT ONLY: uid=$uid, jobId=$jobId\n", FILE_APPEND);
                
                // We'll call run_analysis.php but we need it to support a 'text_only' flag or similar
                // Actually, let's just implement a small logic here or tell run_analysis to skip transcription
                $phpPath = PHP_BINARY ?: 'php';
                $cmd = "{$phpPath} \"{$root}/run_analysis.php\" \"{$uid}\" \"TEXT_ONLY\" \"{$jobId}\" > /dev/null 2>&1 &";
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
