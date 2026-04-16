<?php
/**
 * run_analysis.php
 * CLI runner called by api_upload.php to analyze audio in the background.
 * Usage: php run_analysis.php <uid> <audioPath>
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$uid       = $argv[1] ?? null;
$audioPath = $argv[2] ?? null;

if (!$uid || !$audioPath || !file_exists(__DIR__ . '/' . $audioPath)) {
    exit("Invalid arguments\n");
}

require_once __DIR__ . '/core/GeminiService.php';
require_once __DIR__ . '/core/FirebaseService.php';

ignore_user_abort(true);
set_time_limit(1800);

$userDir       = __DIR__ . '/users/';
$userPostsFile = $userDir . $uid . '_posts.json';
$mimeType      = mime_content_type(__DIR__ . '/' . $audioPath);

// Create "解析中" placeholder post
$posts   = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
if (!is_array($posts)) $posts = [];

$jobId = 'job_' . time() . '_' . rand(1000, 9999);
$newPost = [
    'id'            => $jobId,
    'title'         => '🎙 ' . date('H:i') . 'の音声を解析中...',
    'text'          => "Shortcutから受信した音声を解析しています。\n数分後にダッシュボードをリロードしてください。",
    'original_text' => '',
    'content'       => '',
    'date'          => date('Y-m-d'),
    'category'      => 'Voice Memo',
    'status'        => '解析中',
    'audio_file'    => '',
];
$posts[] = $newPost;
file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Upload audio to Firebase Storage
$storagePath = $audioPath;
try {
    $firebase    = new FirebaseService();
    $storagePath = $firebase->uploadAudio(__DIR__ . '/' . $audioPath, $uid);
} catch (Exception $e) {
    // Firebase upload failed – keep local path
}

// Transcribe
try {
    $gemini = new GeminiService();
    $raw    = $gemini->transcribe(__DIR__ . '/' . $audioPath, $mimeType);

    $titlePrompt    = "以下の文字起こしテキストの内容を端的に表す、15文字以下のキャッチーなタイトルを作成してください。結果の文字列のみを出力してください。\n\n" . mb_strimwidth($raw, 0, 5000);
    $generatedTitle = trim($gemini->generateText($titlePrompt, false));
    $generatedTitle = str_replace(["\n", "\r", "\"", "'", "「", "」"], '', $generatedTitle);

    $finalTitle = 'VJ' . date('Ymd') . '_' . $generatedTitle;

    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']         = $finalTitle;
            $p['text']          = $raw;
            $p['original_text'] = $raw;
            $p['content']       = '';
            $p['status']        = '下書き';
            $p['audio_file']    = $storagePath;
            break;
        }
    }
    unset($p);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($storagePath !== $audioPath) {
        @unlink(__DIR__ . '/' . $audioPath);
    }
} catch (Exception $e) {
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']  = '❌ 解析エラー';
            $p['text']   = 'エラー: ' . $e->getMessage();
            $p['status'] = '下書き';
            break;
        }
    }
    unset($p);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
