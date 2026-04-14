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
$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);
$userPostsFile = $userDir . $uid . '_posts.json';

$posts = file_exists($userPostsFile) ? json_decode(file_get_contents($userPostsFile), true) : [];
if (!is_array($posts)) $posts = [];

$jobId = 'job_' . time() . '_' . rand(1000, 9999);
$newPost = [
    'id' => $jobId,
    'title' => '🎙 ' . date('H:i') . 'の音声を解析中...',
    'text' => "AIが文字起こしならびに文脈をインデックスしています。\nバックグラウンドで処理中のため、完了までしばらくお待ちください。\n（60分の音声の場合、数分〜10分程度かかることがあります）\n\n完了すると自動的に下書きに変わります。",
    'original_text' => '',
    'content' => '',
    'date' => date('Y-m-d'),
    'category' => 'Voice Memo',
    'status' => '解析中'
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
    
    // 解析成功：JSON読み直し（並行でユーザーが追加している可能性があるため）
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            // 解析結果を上書きし、ステータスを下書きへ
            $p['title'] = $finalTitle;
            $p['text'] = $raw;
            $p['original_text'] = $raw;
            $p['content'] = ''; // AI生成用コンテンツ
            $p['status'] = '下書き';
            $p['category'] = 'Voice Memo';
            break;
        }
    }
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @unlink($audioPath);

} catch (Exception $e) {
    // 解析失敗
    $posts = json_decode(file_get_contents($userPostsFile), true);
    foreach ($posts as &$p) {
        if (($p['id'] ?? '') === $jobId) {
            $p['title'] = '❌ 解析エラー';
            $p['text'] = 'エラーが発生しました：' . $e->getMessage();
            $p['status'] = '下書き'; // 読めるように下書きにしておく
            break;
        }
    }
    file_put_contents($userPostsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @unlink($audioPath);
}
exit();