<?php
/**
 * debug_analyze.php
 * Admin-only: Runs analysis synchronously in the browser for debugging.
 * Shows every step and any errors directly.
 */
ob_start();

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';
require_once __DIR__ . '/../core/FirebaseService.php';

if ($userPlan !== 'admin') {
    die('Unauthorized');
}

$target_uid = $_GET['uid'] ?? $uid;
$index      = $_GET['index'] ?? null;

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $target_uid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$log = [];
$log[] = "PHP " . PHP_VERSION . " / SAPI=" . PHP_SAPI;
$log[] = "UID: $target_uid";
$log[] = "GeminiService: " . (class_exists('GeminiService') ? 'OK' : 'NOT FOUND');
$log[] = "FirebaseService: " . (class_exists('FirebaseService') ? 'OK' : 'NOT FOUND');

if ($index !== null && isset($posts[$index])) {
    $post      = $posts[$index];
    $audioPath = $post['audio_file'] ?? $post['local_path'] ?? '';
    $jobId     = $post['id'] ?? '';
    $log[] = "Post: {$post['title']} (status={$post['status']})";
    $log[] = "audio_file: $audioPath";
    $log[] = "local file exists: " . (file_exists(__DIR__ . '/../' . $audioPath) ? 'YES' : 'NO');
    $log[] = "abs path tried: " . __DIR__ . '/../' . $audioPath;

    if ($_GET['run'] ?? false) {
        try {
            $gemini   = new GeminiService();
            $ext      = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
            $mimeMap  = ['m4a'=>'audio/mp4','mp4'=>'audio/mp4','mp3'=>'audio/mpeg','wav'=>'audio/wav'];
            $mimeType = $mimeMap[$ext] ?? 'audio/mp4';
            $localPath = __DIR__ . '/../' . $audioPath;

            $log[] = "Starting transcription... ($mimeType)";
            $raw = $gemini->transcribe($localPath, $mimeType);
            $log[] = "Transcription SUCCESS. Length: " . mb_strlen($raw);
            $log[] = "First 300 chars: " . mb_substr($raw, 0, 300);
        } catch (Throwable $e) {
            $log[] = "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            $log[] = $e->getTraceAsString();
        }
    }
} else {
    // List all posts that might need analysis
    $log[] = "--- Posts needing analysis ---";
    foreach ($posts as $i => $p) {
        $status = $p['status'] ?? '';
        $title  = $p['title'] ?? '';
        $audio  = $p['audio_file'] ?? $p['local_path'] ?? '';
        if (in_array($status, ['解析中','エラー']) || preg_match('/^新規録音/', $title)) {
            $localExists = file_exists(__DIR__ . '/../' . $audio) ? '✅' : '❌';
            $log[] = "[$i] $title | status=$status | audio=$audio $localExists";
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $log);
