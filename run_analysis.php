<?php
/**
 * run_analysis.php
 * CLI runner called by api_upload.php to analyze audio in the background.
 * Usage: php run_analysis.php <uid> <audioPath>
 */

if (PHP_SAPI === 'cli') {
    $uid       = $argv[1] ?? null;
    $audioPath = $argv[2] ?? null;
} else {
    $secret = $_POST['secret'] ?? '';
    if ($secret !== 'voyager_internal_exec_1234') {
        http_response_code(403);
        exit('Unauthorized');
    }
    $uid       = $_POST['uid'] ?? null;
    $audioPath = $_POST['audioPath'] ?? null;
    
    // Close connection to allow caller to finish immediately
    header("Connection: close");
    ob_start();
    echo "Started";
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush();
    @ob_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

if (!$uid || !$audioPath || !file_exists(__DIR__ . '/' . $audioPath)) {
    exit("Invalid arguments\n");
}

require_once __DIR__ . '/core/GeminiService.php';
require_once __DIR__ . '/core/FirebaseService.php';

ignore_user_abort(true);
set_time_limit(1800);

$logFile = __DIR__ . '/log/run_analysis_log.txt';
if (!file_exists(__DIR__ . '/log')) mkdir(__DIR__ . '/log', 0755, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - START uid: $uid, audio: $audioPath\n", FILE_APPEND);

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
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribing...\n", FILE_APPEND);
    $raw    = $gemini->transcribe(__DIR__ . '/' . $audioPath, $mimeType);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribed length: " . mb_strlen($raw) . "\n", FILE_APPEND);

    $titlePrompt    = "以下の文字起こしテキストの内容を端的に表す、15文字以下のキャッチーなタイトルを作成してください。結果の文字列のみを出力してください。\n\n" . mb_strimwidth($raw, 0, 5000);
    $generatedTitle = trim($gemini->generateText($titlePrompt, false));
    $generatedTitle = str_replace(["\n", "\r", "\"", "'", "「", "」"], '', $generatedTitle);

    $finalTitle = 'VJ' . date('Ymd') . '_' . $generatedTitle;

    // 記事化（サマリー/清書）の生成
    $articlePrompt = "以下の音声の文字起こしを元に、読みやすく整理された記事（ブログやジャーナル形式）を作成してください。見出しや箇条書きを適宜用いて、元の音声の意図や思考のプロセスが伝わるように構成してください。\n\n" . mb_strimwidth($raw, 0, 8000);
    $article = trim($gemini->generateText($articlePrompt, false));

    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']         = $finalTitle;
            $p['text']          = $article; // 保存先を記事に
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

    // LINEへプッシュ通知
    $lineMapFile = __DIR__ . '/users/line_map.json';
    $lineMap = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) : [];
    $lineUserId = null;
    foreach ($lineMap as $lid => $mappedUid) {
        if ($mappedUid === $uid) {
            $lineUserId = $lid;
            break;
        }
    }

    if ($lineUserId) {
        $configFile = __DIR__ . '/config/line_config.php';
        $lineConfig = file_exists($configFile) ? require $configFile : null;
        if ($lineConfig && !empty($lineConfig['channel_access_token'])) {
            $messageText = "✨ 解析が完了しました！\n\n【{$finalTitle}】\n\n" . mb_strimwidth($article, 0, 800, '...') . "\n\n▼ 続きや編集はダッシュボードから\nhttps://udatsu-voyager.com/mypage/dashboard.php";
            
            $url = 'https://api.line.me/v2/bot/message/push';
            $postData = [
                'to' => $lineUserId,
                'messages' => [['type' => 'text', 'text' => $messageText]]
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=UTF-8',
                'Authorization: Bearer ' . $lineConfig['channel_access_token']
            ]);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_exec($ch);
            curl_close($ch);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Push sent. HTTP: $httpCode. Response: $response\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Missing channel access token\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - LINE user ID not found for uid: $uid\n", FILE_APPEND);
    }

} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
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
