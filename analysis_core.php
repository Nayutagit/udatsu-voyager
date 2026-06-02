<?php
/**
 * analysis_core.php
 * 解析のコアロジック（関数化）。
 * run_analysis.php（CLI/HTTP経由）と retry_analysis.php（AJAX）の両方から呼ばれる。
 * この時点で GeminiService, FirebaseService はロード済みであること。
 */

function run_analysis_for_post(string $uid, string $audioPath, string $jobId, string $originalTitle = ''): void
{
    $root          = __DIR__;
    $userDir       = $root . '/users/';
    $logFile       = $root . '/log/analysis_log.txt';
    $userPostsFile = $userDir . $uid . '_posts.json';

    if (!file_exists($root . '/log')) {
        mkdir($root . '/log', 0777, true);
        chmod($root . '/log', 0777);
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - [run_analysis_for_post] uid=$uid audioPath=$audioPath jobId=$jobId\n", FILE_APPEND);

    $posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
    if (!is_array($posts)) $posts = [];

    $isTextOnlyRetry = ($audioPath === 'TEXT_ONLY');
    $existingText    = '';
    $existingAudio   = '';

    // jobIdが空なら新規作成
    if (empty($jobId)) {
        $jobId   = 'job_' . time() . '_' . rand(1000, 9999);
        $posts[] = [
            'id'            => $jobId,
            'title'         => '🎙 ' . date('H:i') . 'の音声を解析中...',
            'original_title'=> $originalTitle ?: '音声メモ',
            'text'          => "音声を解析中です。数分後にマイページをリロードしてください。",
            'original_text' => '',
            'date'          => date('Y-m-d'),
            'status'        => '解析中',
            'audio_file'    => $audioPath,
        ];
        file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        foreach ($posts as &$p) {
            if (($p['id'] ?? '') === $jobId) {
                $p['status']  = '解析中';
                $p['original_title'] = !empty($originalTitle) ? $originalTitle : ($p['original_title'] ?? '音声メモ');
                $existingText = $p['original_text'] ?? ($p['text'] ?? '');
                $existingAudio = $p['audio_file'] ?? '';
                break;
            }
        }
        unset($p);
        file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $tempLocalPath = null;

    try {
        // --- 文字起こし or テキスト再利用 ---
        if ($isTextOnlyRetry) {
            $raw         = $existingText;
            $storagePath = $existingAudio;
        } else {
            $localPath = $root . '/' . $audioPath;
            $isTemporaryLocal = false;

            // Check if local file is missing but exists on Firebase
            if (!file_exists($localPath)) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Local file missing: $localPath. Checking Firebase...\n", FILE_APPEND);
                try {
                    $firebase = new FirebaseService();
                    if ($firebase->audioExists($audioPath)) {
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Audio exists on Firebase. Downloading...\n", FILE_APPEND);
                        $signedUrl = $firebase->getSignedUrl($audioPath);
                        $tempDir = $root . '/uploads/temp/';
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0777, true);
                            chmod($tempDir, 0777);
                        }
                        
                        $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION)) ?: 'm4a';
                        $tempLocalPath = $tempDir . 'download_' . $jobId . '_' . time() . '.' . $ext;
                        
                        // Download using curl
                        $ch = curl_init($signedUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        $audioData = curl_exec($ch);
                        curl_close($ch);
                        
                        if ($audioData) {
                            file_put_contents($tempLocalPath, $audioData);
                            $localPath = $tempLocalPath;
                            $isTemporaryLocal = true;
                            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Downloaded to temp path: $localPath\n", FILE_APPEND);
                        } else {
                            throw new Exception("Firebaseからの音声データのダウンロードに失敗しました。");
                        }
                    } else {
                        throw new Exception("ローカルファイルもFirebase上の音声ファイルも見つかりません。");
                    }
                } catch (Throwable $fe) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Firebase recovery failed: " . $fe->getMessage() . "\n", FILE_APPEND);
                    throw new Exception("音声ファイルが見つかりません: " . $fe->getMessage());
                }
            }

            $ext       = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
            $mimeMap   = ['m4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
            $mimeType  = $mimeMap[$ext] ?? 'audio/mp4';

            // Firebase アップロード（一時ローカルファイルでない場合のみアップロード実行）
            $storagePath = $audioPath;
            if (!$isTemporaryLocal) {
                try {
                    $firebase    = new FirebaseService();
                    $storagePath = $firebase->uploadAudio($localPath, $uid);
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Firebase upload OK: $storagePath\n", FILE_APPEND);
                } catch (Throwable $fe) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Firebase upload failed (continuing): " . $fe->getMessage() . "\n", FILE_APPEND);
                }
            }

            // 文字起こし
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribing ($mimeType)...\n", FILE_APPEND);
            $gemini = new GeminiService();
            $raw    = $gemini->transcribe($localPath, $mimeType);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transcribed: " . mb_strlen($raw) . " chars\n", FILE_APPEND);
        }

        if (empty(trim($raw))) {
            throw new Exception("文字起こし結果が空です。");
        }

        // --- AI解析 ---
        $titleCtx = '';
        if (!empty($originalTitle) && strpos($originalTitle, 'voice_message') === false) {
            $titleCtx = "なお、元のファイル名は「{$originalTitle}」です。内容を表していれば参考にしてください。";
        }

        $prompt = "以下の音声文字起こしから、JSON形式で次の4項目を出力してください。\n"
                . "【重要指示】記述は必ず一人称（自分視点）かつ丁寧語（です・ます調）で行ってください。「ユーザーは〜」ではなく「私は〜」「〜だと考えました」といった、本人の丁寧な独白・思考ログとして整理してください。文章全体の文末は必ず「です・ます」で統一し、「だ・である」調は絶対に使用しないでください。\n\n"
                . "1. title: 内容を端的に表す15文字以内のタイトル。{$titleCtx}\n"
                . "2. date: 音声内に収録日の言及があれば「YYYYMMDD」の8桁。なければ「UNKNOWN」\n"
                . "3. summary: 内容を100〜200文字程度で要約したもの。マイページのカードに表示されます。\n"
                . "4. article: ブログ/ジャーナル形式の読みやすい記事（見出し・箇条書き可）\n\n"
                . "出力はJSONオブジェクトのみ。\n\n文字起こし:\n" . mb_strimwidth($raw, 0, 8000);

        $gemini     = new GeminiService();
        $jsonResult = trim($gemini->generateText($prompt, false));
        $jsonResult = preg_replace('/^```(?:json)?\s*/i', '', $jsonResult);
        $jsonResult = preg_replace('/\s*```$/i',          '', $jsonResult);

        $data = json_decode($jsonResult, true);
        if ($data === null && preg_match('/\{.*\}/s', $jsonResult, $m)) {
            $data = json_decode($m[0], true);
        }

        $generatedTitle = str_replace(["\n","\r","\"","'","「","」"], '', $data['title'] ?? '新規投稿');
        $extractedDate  = $data['date'] ?? 'UNKNOWN';
        if (preg_match('/^\d{8}$/', $extractedDate)) {
            $datePfx     = $extractedDate;
            $postDateStr = substr($extractedDate, 0, 4) . '-' . substr($extractedDate, 4, 2) . '-' . substr($extractedDate, 6, 2);
        } else {
            $datePfx     = date('Ymd');
            $postDateStr = date('Y-m-d');
        }
        $finalTitle = 'VJ' . $datePfx . '_' . $generatedTitle;
        $article    = !empty($data['article']) ? $data['article'] : $raw;
        $summary    = $data['summary'] ?? '';

        // RE-READ the file to prevent overwriting changes (like eyecatch images) made during background analysis
        $posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
        if (!is_array($posts)) $posts = [];
        
        $found = false;
        foreach ($posts as &$p) {
            if (($p['id'] ?? '') === $jobId) {
                $p['title']          = $finalTitle;
                $p['original_title'] = !empty($originalTitle) ? $originalTitle : ($p['original_title'] ?? '音声メモ');
                $p['text']           = $article;
                $p['summary']        = $summary;
                $p['original_text']  = $raw;
                $p['date']           = $postDateStr;
                $p['status']         = 'Inbox';
                if (!empty($storagePath)) $p['audio_file'] = $storagePath;
                unset($p['local_path']);
                $found = true;
                break;
            }
        }
        if (!$found) {
            // 投稿が見つからない場合は、新規作成（これが無いとマイページに現れない）
            array_unshift($posts, [
                'id'             => $jobId,
                'title'          => $finalTitle,
                'original_title' => $originalTitle ?: '音声メモ',
                'text'           => $article,
                'summary'        => $summary,
                'date'           => $postDateStr,
                'category'       => 'ボイスジャーナル',
                'audio_file'     => $storagePath,
                'status'         => 'Inbox',
                'original_text'  => $raw,
                'is_shared'      => 0,
            ]);
        }
        unset($p);
        file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SAVED to $userPostsFile (Total: " . count($posts) . ")\n", FILE_APPEND);

        // ローカルファイル削除（Firebaseにアップ済なら）
        if ($storagePath !== $audioPath) {
            @unlink($root . '/' . $audioPath);
        }

        file_put_contents($logFile, date('Y-m-d H:i:s') . " - DONE: $finalTitle\n", FILE_APPEND);

        // --- LINE通知 ---
        _send_line_push($uid, $finalTitle, $article, $logFile, $root);

    } catch (Throwable $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        $posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : null;
        if (is_array($posts)) {
            foreach ($posts as &$p) {
                if (($p['id'] ?? '') === $jobId) {
                    $p['status'] = 'エラー';
                    break;
                }
            }
            unset($p);
            file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } finally {
        if (!empty($tempLocalPath) && file_exists($tempLocalPath)) {
            @unlink($tempLocalPath);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Cleaned up temp local file: $tempLocalPath\n", FILE_APPEND);
        }
        // Rotate the analysis log file to keep disk usage under control
        if (function_exists('rotate_log_file')) {
            rotate_log_file($logFile);
        }
    }
}

function _send_line_push(string $uid, string $title, string $article, string $logFile, string $root): void
{
    $lineMapFile = $root . '/users/line_map.json';
    $lineMap     = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) : [];
    $lineUserId  = null;
    foreach ($lineMap as $lid => $mappedUid) {
        if ($mappedUid === $uid) { $lineUserId = $lid; break; }
    }
    if (!$lineUserId) return;

    $configFile = $root . '/config/line_config.php';
    $lineConfig = file_exists($configFile) ? require $configFile : null;
    if (!$lineConfig || empty($lineConfig['channel_access_token'])) return;

    $mypageUrl   = "https://udatsu-voyager.com/mypage/mypage.php?openExternalBrowser=1";
    $fullMessage = "✨ 解析が完了しました！\n\n【{$title}】\n\n" . $article . "\n\n▼ マイページ\n" . $mypageUrl;

    $messages = [];
    $chunk    = '';
    foreach (explode("\n", $fullMessage) as $line) {
        if (mb_strlen($chunk . $line . "\n") > 4800) {
            $messages[] = ['type' => 'text', 'text' => trim($chunk)];
            $chunk = $line . "\n";
            if (count($messages) >= 4) break;
        } else {
            $chunk .= $line . "\n";
        }
    }
    if (!empty($chunk)) {
        $messages[] = ['type' => 'text', 'text' => mb_strimwidth(trim($chunk), 0, 4800, '...')];
    }

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS     => json_encode(['to' => $lineUserId, 'messages' => array_slice($messages, 0, 5)]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=UTF-8',
            'Authorization: Bearer ' . $lineConfig['channel_access_token'],
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - LINE push HTTP: $code\n", FILE_APPEND);
}
