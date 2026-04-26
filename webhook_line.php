<?php
/**
 * Udatsu Voyager - LINE Webhook
 * LINE アカウントからのメッセージを受信し、音声だった場合は自動解析に回す
 */
$configFile = __DIR__ . '/config/line_config.php';
$lineConfig = file_exists($configFile) ? require $configFile : null;

if (!$lineConfig || empty($lineConfig['channel_access_token'])) {
    http_response_code(500);
    exit('LINE Configuration is missing.');
}

$channelAccessToken = $lineConfig['channel_access_token'];

$payload = file_get_contents('php://input');
file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . "\n" . $payload . "\n\n", FILE_APPEND);
$events = json_decode($payload, true);

if (!isset($events['events'])) {
    http_response_code(200);
    exit();
}

$lineMapFile = __DIR__ . '/users/line_map.json';
$lineMap = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) : [];

foreach ($events['events'] as $event) {
    if ($event['type'] !== 'message' && $event['type'] !== 'follow') {
        continue;
    }
    
    $replyToken = $event['replyToken'] ?? '';
    $lineUserId = $event['source']['userId'];
    $messageType = $event['message']['type'] ?? '';
    
    // Check if user is linked
    $uid = $lineMap[$lineUserId] ?? null;

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
            $msgText = "⚠️ まだアカウントが連携されていません！\n\n今お送りいただいた音声は保存されていません。以下のURLから連携を完了させた後、もう一度音声を送信してください。\n\n{$linkUrl}";
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
        replyLineMessage($channelAccessToken, $replyToken, "🎙 音声を受信しました！\nAIが文字起こしと記事化を行っています。\n\n数分後にこのLINEへ直接、清書された記事が届きますので、そのままお待ちください🚀");
        
        // Download audio/file data
        $audioData = downloadLineAudio($channelAccessToken, $messageId);
        if ($audioData) {
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Audio downloaded successfully. Size: " . strlen($audioData) . "\n", FILE_APPEND);
            $uploadDir = __DIR__ . '/uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
            
            // Use safe filename based on original extension
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION) ?: 'm4a';
            $filename = 'line_' . $messageId . '_' . time() . '.' . $extension;
            $targetPath = 'uploads/' . $filename;
            file_put_contents(__DIR__ . '/' . $targetPath, $audioData);
            
            // Kick background analysis via internal HTTP POST (avoids CLI PHP version issues)
            $analyzeUrl = 'https://udatsu-voyager.com/run_analysis.php';
            $ch = curl_init($analyzeUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'secret' => 'voyager_internal_exec_1234',
                'uid' => $uid,
                'audioPath' => $targetPath
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Disconnect immediately
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
            
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - HTTP Trigger sent to run_analysis.php for $targetPath\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . '/webhook_debug.txt', date('Y-m-d H:i:s') . " - Failed to download audio. Message ID: $messageId\n", FILE_APPEND);
        }
    } else {
        replyLineMessage($channelAccessToken, $replyToken, "音声ファイル（ボイスメッセージなど）を送ってください！");
    }
}

http_response_code(200);
echo "OK";

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function downloadLineAudio($accessToken, $messageId) {
    $url = "https://api-data.line.me/v2/bot/message/{$messageId}/content";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
