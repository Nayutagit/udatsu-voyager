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

// === ダッシュボードに「解析中」のダミーデータを即時作成 ===
require_once __DIR__ . '/core/FirebaseService.php';
$firebase = new FirebaseService();
$jobId = 'job_' . time() . '_' . rand(1000, 9999);

$newPost = [
    'id' => $jobId,
    'title' => '🎙 ' . date('H:i') . 'の音声を解析中...',
    'text' => "AIが文字起こしならびに文脈をインデックスしています。\nバックグラウンドで処理中のため、完了までしばらくお待ちください。\n（60分の音声の場合、数分〜10分程度かかることがあります）\n\n完了すると自動的に下書きに変わります。",
    'original_text' => '',
    'content' => '',
    'date' => date('Y-m-d'),
    'category' => 'Voice Memo',
    'status' => '解析中',
    'audio_file' => '' // We will set this after upload
];

$firebase->savePost($uid, $jobId, $newPost);

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
// ユーザーがブラウザを閉じても稼働し続ける
// --------------------------------------------------------
ignore_user_abort(true);
set_time_limit(1800); // 30分間はタイムアウトさせない

try {
    $gemini = new GeminiService();
    $raw = $gemini->transcribe($audioPath, $mimeType);
    
    // タイトルの自動生成 (テキストの冒頭5000文字を使って短いタイトルを作る)
    $titlePrompt = "以下の文字起こしテキストの内容を端的に表す、15文字以下のシンプルでキャッチーなタイトルを作成してください。結果の文字列のみを出力してください。\n\n" . mb_strimwidth($raw, 0, 5000);
    try {
        $generatedTitle = $gemini->generateText($titlePrompt, false);
        // 余計な記号などを除去
        $generatedTitle = str_replace(["\n", "\r", "\"", "'", "「", "」"], "", trim($generatedTitle));
    } catch (Exception $e) {
        $generatedTitle = mb_strimwidth(str_replace("\n", " ", $raw), 0, 15, '');
    }
    
    // VJ20260414_タイトル の形式にする
    $datePrefix = "VJ" . date('Ymd');
    $finalTitle = $datePrefix . "_" . $generatedTitle;
    
    // 音声ファイルをFirebase Storageにアップロード
    $bucket = $firebase->getBucket();
    $storagePath = "audio/{$uid}/" . basename($audioPath);
    try {
        $bucket->upload(
            fopen($audioPath, 'r'),
            ['name' => $storagePath]
        );
        $audioUrlRef = $storagePath;
    } catch (Exception $e) {
        $audioUrlRef = '';
    }
    
    // 解析成功：Firestore上書き
    $post = $firebase->getPost($uid, $jobId) ?? $newPost;
    $post['title'] = $finalTitle;
    $post['text'] = $raw;
    $post['original_text'] = $raw;
    $post['content'] = ''; 
    $post['status'] = '下書き';
    $post['category'] = 'Voice Memo';
    $post['audio_file'] = $audioUrlRef;
    
    $firebase->savePost($uid, $jobId, $post);
    @unlink($audioPath); // ローカルファイルを削除！

} catch (Exception $e) {
    // 解析失敗
    $post = $firebase->getPost($uid, $jobId) ?? $newPost;
    $post['title'] = '❌ 解析エラー';
    $post['text'] = 'エラーが発生しました：' . $e->getMessage();
    $post['status'] = '下書き'; 
    $firebase->savePost($uid, $jobId, $post);
    @unlink($audioPath);
}
exit();