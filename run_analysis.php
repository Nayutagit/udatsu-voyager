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
            curl_exec($ch);
            curl_close($ch);
        }
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
