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

    $combinedPrompt = "以下の音声の文字起こしを元に、以下の4つの情報をJSON形式で出力してください。\n" .
        "【重要指示】記述は必ず一人称（自分視点）で行ってください。「ユーザーは〜」ではなく「私は〜」「〜だと考えた」といった、本人の独白・思考ログとして整理してください。\n\n" .
        "1. title: 文字起こし内容を端的に表す15文字以下のキャッチーなタイトル\n" .
        "2. date: 音声内で言及された収録日（〇月〇日など）があれば「YYYYMMDD」の8桁の数字（年は今年を想定）。言及がなければ「UNKNOWN」\n" .
        "3. summary: 内容を100〜200文字程度で要約したもの。マイページのカードに表示されます。\n" .
        "4. article: 読みやすく整理された記事（ブログやジャーナル形式）。見出しや箇条書きを適宜用いて、元の音声の意図や思考プロセスが伝わるように構成すること。\n\n" .
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
    
    // Fallback logic for article
    $article = $data['article'] ?? '';
    if (empty($article) || $article === '(要約解析失敗)') {
        $article = $raw;
    }

    // ----------------------------------------------------------------
    // STEP 3: 解析成功 → JSONを更新、ローカル音声を削除
    // ----------------------------------------------------------------
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title']         = $finalTitle;
            $p['text']          = $article;
            $p['summary']       = $data['summary'] ?? '';
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
            $p['title']      = '（解析中...）';
            $p['text']       = '処理中に問題が発生しました。';
            $p['status']     = '解析中';
            $p['audio_file'] = $storagePath;
            break;
        }
    }
    unset($p);
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
exit();