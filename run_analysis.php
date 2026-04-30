<?php
/**
 * run_analysis.php
 * CLI runner called by api_upload.php to analyze audio in the background.
 * Usage: php run_analysis.php <uid> <audioPath>
 */

if (PHP_SAPI === 'cli') {
    $uid           = $argv[1] ?? null;
    $audioPath     = $argv[2] ?? null;
    $jobId         = $argv[3] ?? null; // Optional jobId to retry
    $originalTitle = $argv[4] ?? null; // Optional original filename
} else {
    $secret = $_POST['secret'] ?? '';
    if ($secret !== 'voyager_internal_exec_1234') {
        http_response_code(403);
        exit('Unauthorized');
    }
    $uid           = $_POST['uid'] ?? null;
    $audioPath     = $_POST['audioPath'] ?? null;
    $jobId         = $_POST['jobId'] ?? null; // Optional jobId to retry
    $originalTitle = $_POST['originalTitle'] ?? null;

    // Close connection immediately so webhook can return 200 to LINE
    // while we continue processing in the background
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

// === EARLY LOG (before bootstrap, to detect crash location) ===
$logDir = __DIR__ . '/log';
$earlyLog = $logDir . '/analysis_log.txt';
if (!file_exists($logDir)) {
    @mkdir($logDir, 0775, true);
}

// Fallback to a temporary file if the log directory is not writable
if (!is_writable($logDir) && !file_exists($earlyLog)) {
    $earlyLog = sys_get_temp_dir() . '/udatsu_analysis_' . date('Ymd') . '.log';
}

file_put_contents($earlyLog, date('Y-m-d H:i:s') . " - run_analysis.php started. SAPI=" . PHP_SAPI . " uid=$uid audioPath=$audioPath jobId=$jobId\n", FILE_APPEND);

try {
    require_once __DIR__ . '/core/bootstrap.php';
    require_once __DIR__ . '/core/GeminiService.php';
    require_once __DIR__ . '/core/FirebaseService.php';
    file_put_contents($earlyLog, date('Y-m-d H:i:s') . " - Dependencies loaded OK\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents($earlyLog, date('Y-m-d H:i:s') . " - LOAD FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
    exit(1);
}

// Load dependencies (after connection is closed)
$userDir = __DIR__ . '/users/';
$logFile = $earlyLog;

// Create "解析中" placeholder post ONLY if we don't have a jobId to retry
$userPostsFile = $userDir . $uid . '_posts.json';
$posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
if (!is_array($posts)) $posts = [];

$isTextOnlyRetry = ($audioPath === 'TEXT_ONLY');
$existingText = '';
$existingAudioFile = '';

// Concurrency Control: Prevent multiple processes for the same jobId
$lockFile = __DIR__ . '/log/job_' . ($jobId ?: 'unknown') . '.lock';
$lockFp = fopen($lockFile, 'w');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Job $jobId is already being processed. Exiting.\n", FILE_APPEND);
    exit(0);
}
fwrite($lockFp, getmypid());

if (empty($jobId)) {
    $jobId = 'job_' . time() . '_' . rand(1000, 9999);
    $newPost = [
        'id'            => $jobId,
        'title'         => '🎙 ' . date('H:i') . 'の音声を解析中...',
        'text'          => "Shortcutから受信した音声を解析しています。\n数分後にマイページをリロードしてください。",
        'original_text' => '',
        'content'       => '',
        'date'          => date('Y-m-d'),
        'category'      => 'ボイスジャーナル',
        'status'        => '解析中',
        'audio_file'    => '',
        'local_path'    => $audioPath, // Store for retry
    ];
    array_unshift($posts, $newPost);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
} else {
    // If retrying, update status to '解析中' for the existing job
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['status'] = '解析中';
            $existingText = !empty($p['original_text']) ? $p['original_text'] : ($p['text'] ?? '');
            $existingAudioFile = $p['audio_file'] ?? '';
            break;
        }
    }
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Upload/Transcribe logic
if ($isTextOnlyRetry) {
    $raw = $existingText;
    $storagePath = $existingAudioFile;
} else {
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
        $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
        $mimeMap = ['m4a'=>'audio/mp4','mp4'=>'audio/mp4','mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'audio/webm'];
        $mimeType = $mimeMap[$ext] ?? 'audio/mp4';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribing: $audioPath ($mimeType)\n", FILE_APPEND);
        $raw    = $gemini->transcribe(__DIR__ . '/' . $audioPath, $mimeType);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribed length: " . mb_strlen($raw) . "\n", FILE_APPEND);
    } catch (Exception $e) {
        throw new Exception("文字起こしに失敗しました: " . $e->getMessage());
    }
}

// Analysis
try {
    $gemini = new GeminiService();

    $titleContext = "";
    if (!empty($originalTitle) && strpos($originalTitle, 'voice_message') === false) {
        $titleContext = "なお、この音声の元のファイル名は「{$originalTitle}」です。この名前が内容を表している場合は参考にし、より適切なタイトルを生成してください。";
    }

    $combinedPrompt = "以下の音声の文字起こしを元に、以下の3つの情報をJSON形式で出力してください。\n" .
        "1. title: 文字起こし内容を端的に表す15文字以下のキャッチーなタイトル。{$titleContext}\n" .
        "2. date: 音声内で言及された収録日（〇月〇日など）があれば「YYYYMMDD」の8桁の数字（年は今年を想定）。言及がなければ「UNKNOWN」\n" .
        "3. article: 読みやすく整理された記事（ブログやジャーナル形式）。見出しや箇条書きを適宜用いて、元の音声の意図や思考プロセスが伝わるように構成すること。\n\n" .
        "出力は必ずJSONオブジェクトのみにしてください。\n\n" .
        "文字起こし:\n" . mb_strimwidth($raw, 0, 8000);

    $jsonResult = trim($gemini->generateText($combinedPrompt, false));
    
    // Clean up potential markdown formatting
    $jsonResult = preg_replace('/^```(?:json)?\s*/i', '', $jsonResult);
    $jsonResult = preg_replace('/\s*```$/i', '', $jsonResult);
    
    $data = json_decode($jsonResult, true);
    
    // If json_decode fails, try harder to find JSON within the text
    if ($data === null) {
        if (preg_match('/\{.*\}/s', $jsonResult, $matches)) {
            $data = json_decode($matches[0], true);
        }
    }
    
    $generatedTitle = $data['title'] ?? '新規投稿';
    $generatedTitle = str_replace(["\n", "\r", "\"", "'", "「", "」"], '', $generatedTitle);
    
    $extractedDate = $data['date'] ?? 'UNKNOWN';
    if ($extractedDate !== 'UNKNOWN' && preg_match('/^\d{8}$/', $extractedDate)) {
        $datePrefixStr = $extractedDate;
        $postDateStr = substr($extractedDate, 0, 4) . '-' . substr($extractedDate, 4, 2) . '-' . substr($extractedDate, 6, 2);
    } else {
        $datePrefixStr = date('Ymd');
        $postDateStr = date('Y-m-d');
    }
    
    $finalTitle = 'VJ' . $datePrefixStr . '_' . $generatedTitle;
    
    // Fallback logic for article: if AI fails to generate properly, use the raw transcript
    $article = $data['article'] ?? '';
    if (empty($article) || $article === '(要約解析失敗)') {
        $article = $raw;
    }

    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']         = $finalTitle;
            $p['text']          = $article;
            $p['original_text'] = $raw;
            $p['content']       = '';
            $p['date']          = $postDateStr;

            $p['status']        = 'Inbox';
            $p['audio_file']    = $storagePath;
            unset($p['local_path']); // Success! No need for local path anymore
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
            $mypageUrl = "https://udatsu-voyager.com/mypage/mypage.php?openExternalBrowser=1";
            $fullMessage = "✨ 解析が完了しました！\n\n【{$finalTitle}】\n\n" . $article . "\n\n▼ 続きや編集はマイページから\n" . $mypageUrl;
            
            // LINE allows max 5000 chars per message object, up to 5 objects per push request
            $messages = [];
            $currentChunk = "";
            $lines = explode("\n", $fullMessage);
            foreach ($lines as $line) {
                if (mb_strlen($currentChunk . $line . "\n") > 4800) {
                    $messages[] = ['type' => 'text', 'text' => trim($currentChunk)];
                    $currentChunk = $line . "\n";
                    if (count($messages) >= 4) { // Save last slot for the rest
                        break; 
                    }
                } else {
                    $currentChunk .= $line . "\n";
                }
            }
            if (!empty($currentChunk)) {
                // If we reached the 5th message slot but still have too much text, just truncate the very end
                if (mb_strlen($currentChunk) > 4800) {
                    $currentChunk = mb_strimwidth($currentChunk, 0, 4800, '...');
                }
                $messages[] = ['type' => 'text', 'text' => trim($currentChunk)];
            }
            
            $url = 'https://api.line.me/v2/bot/message/push';
            $postData = [
                'to' => $lineUserId,
                'messages' => array_slice($messages, 0, 5)
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Required for XServer environment
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=UTF-8',
                'Authorization: Bearer ' . $lineConfig['channel_access_token']
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Must be AFTER curl_exec
            $curlError = curl_error($ch);
            curl_close($ch);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Push sent. HTTP: $httpCode. Error: $curlError. Response: $response\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Missing channel access token\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - LINE user ID not found for uid: $uid\n", FILE_APPEND);
    }

    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        @unlink($lockFile);
    }
} catch (Throwable $e) {
    $errorMsg = $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $errorMsg . "\n", FILE_APPEND);
    
    // Attempt to log trace for deep debugging
    file_put_contents($logFile, $e->getTraceAsString() . "\n", FILE_APPEND);

    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']  = '❌ 解析エラー';
            $p['status'] = 'エラー'; // Set to 'エラー' so user knows it failed, or '解析中' to retry
            $p['text']   = "解析中にエラーが発生しました: " . $e->getMessage();
            break;
        }
    }
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        @unlink($lockFile);
    }
}

