<?php
/**
 * run_analysis.php
 * CLI runner called by api_upload.php to analyze audio in the background.
 * Usage: php run_analysis.php <uid> <audioPath>
 */

if (PHP_SAPI === 'cli') {
    $uid           = $argv[1] ?? '';
    $audioPath     = $argv[2] ?? '';
    $jobId         = $argv[3] ?? '';
    $originalTitle = $argv[4] ?? '';
} else {
    $secret = $_POST['secret'] ?? '';
    if ($secret !== 'voyager_internal_exec_1234') {
        http_response_code(403);
        exit('Unauthorized');
    }
    // Set variables if not already set by an inline caller
    if (!isset($uid)) $uid = $_POST['uid'] ?? '';
    if (!isset($audioPath)) $audioPath = $_POST['audioPath'] ?? '';
    if (!isset($jobId)) $jobId = $_POST['jobId'] ?? '';
    if (!isset($originalTitle)) $originalTitle = $_POST['originalTitle'] ?? '';

    // Only close connection if headers haven't been sent yet
    if (!headers_sent()) {
        ignore_user_abort(true);
        set_time_limit(300);
        ob_start();
        echo 'OK';
        $size = ob_get_length();
        header('Content-Length: ' . $size);
        header('Connection: close');
        ob_end_flush();
        flush();
    }
}

// Load dependencies
// Save values to prevent bootstrap.php from overwriting them with session data
$_savedUid = $uid;
$_savedAudioPath = $audioPath;
$_savedJobId = $jobId;

if (PHP_SAPI === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = '127.0.0.1:8000';
}

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/GeminiService.php';
require_once __DIR__ . '/core/FirebaseService.php';

// Restore
$uid = $_savedUid;
$audioPath = $_savedAudioPath;
$jobId = $_savedJobId;
$originalTitle = $originalTitle ?? ''; // Passed from POST/CLI

require_once __DIR__ . '/core/GeminiService.php';
require_once __DIR__ . '/core/FirebaseService.php';
require_once __DIR__ . '/analysis_core.php';

// Execute using the core function
run_analysis_for_post($uid, $audioPath, $jobId, $originalTitle);

