<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/core/GeminiService.php';

// === 音声ファイルとセッションの確認 ===
$audioPath = $_SESSION["audio_path"] ?? '';
$uid = $_SESSION["uid"] ?? null;

if (!$audioPath || !file_exists($audioPath) || !$uid) {
    echo json_encode(["status" => "error", "message" => "音声ファイルがないかセッション切れです。"]);
    exit();
}

// === ダッシュボードに「解析中」のダミーデータを即時作成 (JSONベース) ===
$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);
$userPostsFile = $userDir . $uid . '_posts.json';

$posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
if (!is_array($posts)) $posts = [];

$jobId = 'job_' . time() . '_' . rand(1000, 9999);
$newPost = [
    'id'            => $jobId,
    'title'         => '🎙 ' . date('H:i') . 'の音声を解析中...',
    'text'          => "AIが文字起こしならびに文脈をインデックスしています。\nバックグラウンドで処理中のため、完了までしばらくお待ちください。\n（60分の音声の場合、数分〜10分程度かかることがあります）\n\n完了すると自動的に Inbox に入ります。",
    'original_text' => '',
    'content'       => '',
    'date'          => date('Y-m-d'),
    'category'      => 'Voice Memo',
    'status'        => '解析中',
    'audio_file'    => '', // Firebase Storage pathに後で書き換え
];
$posts[] = $newPost;
file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 非同期実行のために変数待避＆セッションロック解放
$mimeType = mime_content_type($audioPath);
unset($_SESSION["audio_path"]);
unset($_SESSION["original_name"]);
session_write_close();

// === ブラウザへの通信をここで強制切断（非同期化） ===
echo json_encode(["status" => "async_started", "job_id" => $jobId]);
if (ob_get_level() > 0) {
    header("Connection: close");
    header("Content-Length: " . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();
}
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// --------------------------------------------------------
// 以降は完全にバックグラウンド（サーバー裏側）でのみ実行される
// --------------------------------------------------------
ignore_user_abort(true);
set_time_limit(1800);

// ----------------------------------------------------------------
// STEP 1: 音声をFirebase Storageにアップロード（バックグラウンドで）
// ----------------------------------------------------------------
$storagePath = '';
try {
    require_once __DIR__ . '/core/FirebaseService.php';
    $firebase = new FirebaseService();
    $storagePath = $firebase->uploadAudio($audioPath, $uid);
} catch (Exception $e) {
    // Firebase Storageへのアップロードに失敗しても解析は続行する
    $storagePath = $audioPath; // フォールバック：ローカルパスをそのまま使う
}

// ----------------------------------------------------------------
// STEP 2: Geminiで音声を文字起こし
// ----------------------------------------------------------------
try {
    $gemini = new GeminiService();
    $raw = $gemini->transcribe($audioPath, $mimeType);

    // タイトルの自動生成
    $titlePrompt = "以下の文字起こしテキストの内容を端的に表す、15文字以下のシンプルでキャッチーなタイトルを作成してください。結果の文字列のみを出力してください。\n\n" . mb_strimwidth($raw, 0, 5000);
    try {
        $generatedTitle = $gemini->generateText($titlePrompt, false);
        $generatedTitle = str_replace(["\n", "\r", "\"", "'", "「", "」"], "", trim($generatedTitle));
    } catch (Exception $e) {
        $generatedTitle = mb_strimwidth(str_replace("\n", " ", $raw), 0, 15, '');
    }

    // 要約の自動生成
    $summaryPrompt = "以下の文字起こしテキストの要点を、3つの短い箇条書き（-）で簡潔に要約してください。余計な前置きは不要です。：\n\n" . mb_strimwidth($raw, 0, 5000);
    try {
        $generatedSummary = trim($gemini->generateText($summaryPrompt, false));
    } catch (Exception $e) {
        $generatedSummary = '';
    }

    // 収録日の抽出
    $datePrompt = "以下の文字起こしテキストから、収録された日付（〇月〇日など）が明確に読み取れる場合は、その日付を「YYYYMMDD」の8桁の数字（年は今年を想定）で出力してください。日付が全く言及されていない場合は「UNKNOWN」と出力してください。\n\n" . mb_strimwidth($raw, 0, 5000);
    try {
        $extractedDate = trim($gemini->generateText($datePrompt, false));
    } catch (Exception $e) {
        $extractedDate = 'UNKNOWN';
    }
    
    if ($extractedDate !== 'UNKNOWN' && preg_match('/^\d{8}$/', $extractedDate)) {
        $datePrefixStr = $extractedDate;
        $postDateStr = substr($extractedDate, 0, 4) . '-' . substr($extractedDate, 4, 2) . '-' . substr($extractedDate, 6, 2);
    } else {
        $datePrefixStr = date('Ymd');
        $postDateStr = date('Y-m-d');
    }

    $datePrefix = "VJ" . $datePrefixStr;
    $finalTitle  = $datePrefix . "_" . $generatedTitle;

    // ----------------------------------------------------------------
    // STEP 3: 解析成功 → JSONを更新、ローカル音声を削除
    // ----------------------------------------------------------------
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']         = $finalTitle;
            $p['text']          = $raw;
            $p['summary']       = $generatedSummary;
            $p['original_text'] = $raw;
            $p['content']       = '';
            $p['date']          = $postDateStr;
            $p['status']        = 'Inbox';
            $p['category']      = 'ボイスジャーナル';
            $p['audio_file']    = $storagePath; // Firebase Storage path or local fallback
            break;
        }
    }
    unset($p);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Firebase Storageへのアップロードが成功していればローカルファイルを削除
    if ($storagePath !== $audioPath) {
        @unlink($audioPath);
    }

} catch (Exception $e) {
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']      = '❌ 解析エラー';
            $p['text']       = 'エラーが発生しました：' . $e->getMessage();
            $p['status']     = 'Inbox';
            $p['audio_file'] = $storagePath;
            break;
        }
    }
    unset($p);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
exit();