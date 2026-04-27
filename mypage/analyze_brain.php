<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';

header('Content-Type: application/json');

if (empty($uid)) {
    echo json_encode(["status" => "error", "message" => "認証されていません"]);
    exit();
}

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$dictFile = $userDir . $uid . '_dictionary.json';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) {
    $posts = [];
}

// 思考データを集約 (エラー投稿と削除済は除外、最新から最大50件)
$validPosts = array_filter($posts, function($p) {
    $status = $p['status'] ?? '';
    return !in_array($status, ['エラー', '削除済', '解析中']);
});
$validPosts = array_reverse($validPosts);
$validPosts = array_slice($validPosts, 0, 50);

if (count($validPosts) === 0) {
    echo json_encode(["status" => "error", "message" => "分析に十分なデータがありません。"]);
    exit();
}

$aggregatedText = "";
foreach ($validPosts as $p) {
    $title = $p['title'] ?? '';
    // 整文があれば整文を優先、なければ元のテキストを利用
    $text = !empty($p['refined_text']) ? $p['refined_text'] : ($p['original_text'] ?? $p['text'] ?? '');
    $aggregatedText .= "【件名: {$title}】\n{$text}\n\n";
}

// トークン節約のため最大文字数制限をかける（約2万文字）
$aggregatedText = mb_strimwidth($aggregatedText, 0, 20000);

$profileFile = $userDir . $uid . '_thought_profile.json';
$existingProfile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : null;
$existingMd = $existingProfile['markdown'] ?? '';

$mdContext = $existingMd ? "\n\n【過去の思考DNAプロファイル（これまでの全データを統合した分析結果）】\n{$existingMd}" : '';

$prompt = <<<EOT
以下のテキストデータは、ユーザーが日々の思考を記録した音声メモや日記の蓄積です。
このデータを分析し、ユーザーの思考のコア（中心軸）となっている『よく出てくるキーワード・概念を最大5つ』抽出してください。
そして、それぞれのキーワードについて、『ユーザーがその概念についてどう考えているか・どういう哲学を持っているか』を2〜3文で端的に説明してください。

出力形式は以下のJSONフォーマット（キーがキーワード、値が哲学のテキスト）のみで返してください。
Markdownのバッククォート（```json）は含めず、純粋なJSONテキストのみを出力してください。

例:
{
  "効率化": "スペックや手段の最適化よりも、自分がワクワクするかどうかという感情を重視している傾向があります。",
  "自由": "場所に縛られずに働くことを人生の軸としています。"
}

---
【ユーザーの直近の思考データ (より具体的な最新の記録)】
{$aggregatedText}
{$mdContext}
EOT;

// 非同期実行のためにセッションを解放・通信切断
session_write_close();
echo json_encode(["status" => "async_started"]);

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

ignore_user_abort(true);
set_time_limit(600); // 10分

try {
    $gemini = new GeminiService();
    $jsonResult = $gemini->generateText($prompt, true);
    
    // Parse to ensure validity
    $parsed = json_decode($jsonResult, true);
    if (is_array($parsed)) {
        file_put_contents($dictFile, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
} catch (Exception $e) {
    // 失敗時は何もしないか、エラーログ出力
    @file_put_contents($userDir . 'analyze_error.log', date('Y-m-d H:i:s') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
}
exit();
