<?php
/**
 * Udatsu Voyager - LINE Webhook
 * LINE アカウントからのメッセージを受信し、音声だった場合は自動解析に回す
 */
file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Webhook started\n", FILE_APPEND);
$payload = file_get_contents('php://input');
file_put_contents(__DIR__ . '/webhook_debug.txt', "Payload: " . $payload . "\n", FILE_APPEND);

$configFile = __DIR__ . '/config/line_config.php';
$lineConfig = file_exists($configFile) ? require $configFile : null;

if (!$lineConfig || empty($lineConfig['channel_access_token'])) {
    file_put_contents(__DIR__ . '/webhook_debug.txt', "ERROR: LINE Configuration is missing or token is empty.\n", FILE_APPEND);
    http_response_code(500);
    exit('LINE Configuration is missing.');
}

$channelAccessToken = $lineConfig['channel_access_token'];
$events = json_decode($payload, true);

if (!isset($events['events'])) {
    http_response_code(200);
    exit();
}

$lineMapFile = __DIR__ . '/users/line_map.json';
$lineMap = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) : [];

$pendingJobs = [];
foreach ($events['events'] as $event) {
    if ($event['type'] !== 'message' && $event['type'] !== 'follow') {
        continue;
    }
    
    $replyToken = $event['replyToken'] ?? '';
    $lineUserId = $event['source']['userId'];
    $messageType = $event['message']['type'] ?? '';
    
    // Check if user is linked
    $uid = $lineMap[$lineUserId] ?? null;
    
    file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Processing event: type={$event['type']}, messageType={$messageType}, userId={$lineUserId}, uid=" . ($uid ?? 'NULL') . "\n", FILE_APPEND);

    if (!$uid) {
        // Not linked. Generate link URL.
        $linkToken = bin2hex(random_bytes(16));
        $linksFile = __DIR__ . '/users/line_links.json';
        $links = file_exists($linksFile) ? json_decode(file_get_contents($linksFile), true) : [];
        $links[$linkToken] = ['line_user_id' => $lineUserId, 'expires' => time() + 3600];
        file_put_contents($linksFile, json_encode($links));
        
        $linkUrl = "https://udatsu-voyager.com/mypage/line_link.php?token={$linkToken}";
        
        $msgText = "初めまして！Udatsu Voyagerにようこそ。\n\n音声を自動解析するには、まずお持ちのアカウントとの連携が必要です。\n以下のURLを開いて連携を完了させてください。\n(有効期限: 1時間)\n{$linkUrl}";
        if ($event['type'] === 'message' && ($messageType === 'audio' || $messageType === 'file')) {
            $msgText = "⚠️ アカウントが連携されていません！\n\n今お送りいただいた音声は解析できませんでした。\n\n以下のURLからマイページにログインし、連携を完了させてください。\n{$linkUrl}";
        }
        
        replyLineMessage($channelAccessToken, $replyToken, $msgText);
        continue;
    }

    if ($event['type'] === 'follow') {
        replyLineMessage($channelAccessToken, $replyToken, "連携済みです！🎉\nボイスメッセージを吹き込むか、音声ファイル（m4a等）をここに直接送ると、自動でVoyagerに記録・解析されます。");
        continue;
    }

    if ($messageType === 'text') {
        replyLineMessage($channelAccessToken, $replyToken, "連携済みです！🎉\nボイスメッセージを吹き込むか、音声ファイル（m4a等）をここに直接送ると、自動でVoyagerに記録・解析されます。");
    } elseif ($messageType === 'audio' || $messageType === 'file') {
        $messageId = $event['message']['id'];
        $originalFileName = $event['message']['fileName'] ?? 'voice_message.m4a';
        
        // Reply instantly
        replyLineMessage($channelAccessToken, $replyToken, "🎙 音声を受信しました！\n現在AIが文字起こしと記事化を行っています。解析が終わると、このLINEへ直接記事が届きますので、そのまま1〜2分ほどお待ちください🚀");
        
        // Download audio/file data
        $audioData = downloadLineAudio($channelAccessToken, $messageId);
        if ($audioData) {
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Audio downloaded. Size: " . strlen($audioData) . "\n", FILE_APPEND);
            $uploadDir = __DIR__ . '/uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

            $extension    = pathinfo($originalFileName, PATHINFO_EXTENSION) ?: 'm4a';
            $filename     = 'line_' . $messageId . '_' . time() . '.' . $extension;
            $targetPath   = 'uploads/' . $filename;
            $originalTitle = pathinfo($originalFileName, PATHINFO_FILENAME);
            file_put_contents(__DIR__ . '/' . $targetPath, $audioData);

            // ① まず「解析中」投稿レコードをマイページに即作成
            $jobId        = 'job_' . time() . '_' . rand(1000, 9999);
            $postsFile    = __DIR__ . '/users/' . $uid . '_posts.json';
            $posts        = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
            if (!is_array($posts)) $posts = [];
            array_unshift($posts, [
                'id'             => $jobId,
                'title'          => $originalTitle ?: '音声メモ',
                'original_title' => $originalTitle ?: '音声メモ',
                'text'           => '',
                'summary'        => '',
                'date'           => date('Y-m-d'),
                'category'       => 'ボイスジャーナル',
                'audio_file'     => $targetPath,
                'audio'          => '',
                'thumbnail'      => '',
                'status'         => '解析中',
                'is_shared'      => 0,
            ]);
            file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Pending post created: $jobId\n", FILE_APPEND);

            // ② 解析ジョブ情報を記録しておく
            $pendingJobs[] = [
                'uid'           => $uid,
                'audioPath'     => $targetPath,
                'jobId'         => $jobId,
                'originalTitle' => $originalTitle,
            ];
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Pending post created: $jobId\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Failed to download audio. Message ID: $messageId\n", FILE_APPEND);
        }
    } elseif ($messageType === 'image' || $messageType === 'file' || $messageType === 'video') {
        $messageId = $event['message']['id'];
        file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Media received (type=$messageType): $messageId\n", FILE_APPEND);
        
        // Download data
        $mediaData = ($messageType === 'image') ? downloadLineImage($channelAccessToken, $messageId) : downloadLineAudio($channelAccessToken, $messageId);
        
        if ($mediaData) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename     = 'media_' . $messageId . '_' . time() . '.jpg';
            $targetPath   = 'uploads/' . $filename;
            file_put_contents(__DIR__ . '/' . $targetPath, $mediaData);

            $postsFile = __DIR__ . '/users/' . $uid . '_posts.json';
            if (file_exists($postsFile)) {
                $posts = json_decode(file_get_contents($postsFile), true);
                if (is_array($posts) && !empty($posts)) {
                    // 最新の投稿にアイキャッチを設定
                    $posts[0]['thumbnail'] = $targetPath;
                    file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    replyLineMessage($channelAccessToken, $replyToken, "🖼 メディアを受信しました！\nアイキャッチ画像として登録しました🚀");
                }
            }
        }
    } else {
        file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Unhandled type: $messageType\n", FILE_APPEND);
        replyLineMessage($channelAccessToken, $replyToken, "音声メッセージを送ってください！その直後に画像を送るとアイキャッチになります。");
    }
}

// === LINEへ200 OKを即座に返す ===
http_response_code(200);
echo "OK";

// PHP-FPM専用: レスポンスを送信してLINEとの接続を閉じる
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

// === ここから解析処理（LINEは既に切断済み） ===
if (!empty($pendingJobs)) {
    require_once __DIR__ . '/core/functions.php';
    $phpPath = getBestPhpCliPath();
    $runAnalysis = __DIR__ . '/run_analysis.php';
    $logFile = __DIR__ . '/log/analysis_log.txt';
    if (!file_exists(__DIR__ . '/log')) {
        mkdir(__DIR__ . '/log', 0755, true);
    }

    foreach ($pendingJobs as $job) {
        file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Kicking background analysis CLI for {$job['jobId']}\n", FILE_APPEND);
        $cmd = escapeshellarg($phpPath) . ' '
             . escapeshellarg($runAnalysis) . ' '
             . escapeshellarg($job['uid']) . ' '
             . escapeshellarg($job['audioPath']) . ' '
             . escapeshellarg($job['jobId']) . ' '
             . escapeshellarg($job['originalTitle'] ?? '')
             . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Executing: $cmd\n", FILE_APPEND);
        exec($cmd);
    }
}


/* ------------- Helpers ------------- */
function replyLineMessage($accessToken, $replyToken, $text) {
    if (empty($replyToken)) return;
    $url = 'https://api.line.me/v2/bot/message/reply';
    $postData = [
        'replyToken' => $replyToken,
        'messages' => [['type' => 'text', 'text' => $text]]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important for server environments
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: Bearer ' . $accessToken
    ]);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - replyLineMessage error: $error\n", FILE_APPEND);
    }
}

function downloadLineImage($accessToken, $messageId) {
    return downloadLineAudio($accessToken, $messageId); // Same logic for content download
}

function downloadLineAudio($accessToken, $messageId) {
    $url = "https://api-data.line.me/v2/bot/message/{$messageId}/content";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - downloadLineAudio HTTP code: $httpCode, error: $error\n", FILE_APPEND);
    
    return ($httpCode === 200) ? $data : false;
}
