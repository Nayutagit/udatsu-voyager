<?php
/**
 * regen_post_summary.php
 * Re-generates summary (and optionally article) for a single post using existing transcription.
 * Called via AJAX from mypage.php.
 */
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';

header('Content-Type: application/json');

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$index = $_POST['index'] ?? null;
if (!is_numeric($index)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid index']);
    exit;
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

if (!isset($posts[$index])) {
    echo json_encode(['status' => 'error', 'message' => 'Post not found']);
    exit;
}

$post = $posts[$index];

// Use original_text (transcription) as the base. Fall back to text if missing.
$raw = $post['original_text'] ?? '';
if (empty(trim($raw))) {
    $raw = $post['text'] ?? '';
}
if (empty(trim($raw)) || trim($raw) === '(要約解析失敗)') {
    echo json_encode(['status' => 'error', 'message' => '元の文字起こしデータがないため再生成できません。音声から再解析してください。']);
    exit;
}

try {
    $gemini = new GeminiService();

    $combinedPrompt = "以下の音声の文字起こしを元に、日本語で要約を出力してください。\n" .
        "【重要指示】記述は必ず一人称（自分視点）かつ丁寧語（です・ます調）で行ってください。「ユーザーは〜」ではなく「私は〜」「〜だと考えました」といった、本人の丁寧な独白・思考ログとして整理してください。文末は必ず「です・ます」で統一し、「だ・である」調は使用しないでください。\n\n" .
        "出力形式はJSONで以下のようにお願いします：\n" .
        "{\n" .
        "  \"summary\": \"3〜5行の要約テキスト\"\n" .
        "}\n\n" .
        "出力は必ずJSONオブジェクトのみにしてください。\n\n" .
        "文字起こし:\n" . mb_strimwidth($raw, 0, 8000);

    $jsonResult = trim($gemini->generateText($combinedPrompt, false));
    $jsonResult = preg_replace('/^```(?:json)?\s*/i', '', $jsonResult);
    $jsonResult = preg_replace('/\s*```$/i', '', $jsonResult);
    $data = json_decode($jsonResult, true);
    if ($data === null && preg_match('/\{.*\}/s', $jsonResult, $m)) {
        $data = json_decode($m[0], true);
    }

    $newSummary = $data['summary'] ?? '';

    // Update ONLY the summary
    if (!empty($newSummary)) {
        $posts[$index]['summary'] = $newSummary;
    }

    file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status'  => 'ok',
        'summary' => $newSummary,
        'message' => '要約を再生成しました！',
    ]);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => '生成に失敗しました: ' . $e->getMessage()]);
}
