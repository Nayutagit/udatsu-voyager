<?php
/**
 * submit_quiz.php
 * Receives answers from the quiz, saves the session,
 * and regenerates the user's thought profile markdown.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => '認証エラー']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$sessionAnswers = $body['session'] ?? [];

if (empty($sessionAnswers)) {
    echo json_encode(['status' => 'error', 'message' => '回答データがありません']);
    exit();
}

$userDir    = __DIR__ . '/../users/';
$sessFile   = $userDir . $uid . '_quiz_sessions.json';
$profileFile = $userDir . $uid . '_thought_profile.json';
$postsFile  = $userDir . $uid . '_posts.json';

// 1. Save this session
$sessions = file_exists($sessFile) ? json_decode(file_get_contents($sessFile), true) : [];
if (!is_array($sessions)) $sessions = [];
$sessions[] = [
    'date'    => date('Y-m-d H:i:s'),
    'answers' => $sessionAnswers
];
file_put_contents($sessFile, json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Build full answer history for analysis
$allAnswers = '';
foreach ($sessions as $idx => $sess) {
    $allAnswers .= "【セッション " . ($idx + 1) . " (" . $sess['date'] . ")】\n";
    foreach ($sess['answers'] as $a) {
        $allAnswers .= "シナリオ: {$a['scenario']}\n";
        $allAnswers .= "問い: {$a['question']}\n";
        $allAnswers .= "選択: {$a['chosen']} → {$a['chosen_text']}\n\n";
    }
}
$allAnswers = mb_strimwidth($allAnswers, 0, 12000);

// 3. Get VJ data
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
$validPosts = array_filter($posts ?? [], fn($p) => !in_array($p['status'] ?? '', ['解析中', 'エラー', '削除済']));
$validPosts = array_slice(array_reverse(array_values($validPosts)), 0, 20);
$vjText = '';
foreach ($validPosts as $p) {
    $t = mb_strimwidth($p['original_text'] ?? $p['text'] ?? '', 0, 400);
    if ($t) $vjText .= "【{$p['title']}】\n{$t}\n\n";
}
$vjText = mb_strimwidth($vjText, 0, 6000);

// 4. Get existing profile to build upon
$existingProfile = file_exists($profileFile)
    ? json_decode(file_get_contents($profileFile), true)
    : null;
$existingMd = $existingProfile['markdown'] ?? '';

$existingContext = $existingMd
    ? "\n\n【これまでの思考プロファイル（更新・深化させてください）】\n{$existingMd}"
    : '';

$vjContext = $vjText
    ? "\n\n【ユーザーのVoice Journal（思考メモ）】\n{$vjText}"
    : '';

$sessionCount = count($sessions);

$prompt = <<<EOT
あなたはユーザーの思考・価値観・行動パターンを深く分析する心理アナリストです。
以下のデータを総合して、ユーザーの「思考DNA（思考プロファイル）」をMarkdown形式で作成してください。
これは{$sessionCount}回の診断セッションを経て精緻化されたプロファイルです。
{$vjContext}

【全診断セッションの回答データ】
{$allAnswers}
{$existingContext}

【出力ルール】
- Markdownで構造的に記述してください
- セクション：「## 思考の核心」「## 行動パターン」「## 価値観の優先順位」「## 盲点・成長ポイント」「## あなたへの問い」を含めてください
- 「あなたへの問い」セクションには、ユーザーが自己を深く見直すための問いを3つ書いてください
- 分析は表面的でなく、選択パターンの裏にある動機や価値観を推察してください
- 回答数が少ない場合でも、暫定的な分析として正直に記述してください（不確かな点は「〜の傾向が見られます」などと表現）
- Markdownのコードブロックは不要。直接Markdownテキストを出力してください

出力はMarkdownテキストのみです。余計な前置きは不要です。
EOT;

try {
    $gemini  = new GeminiService();
    $md      = trim($gemini->generateText($prompt, false));

    // Save profile
    $profile = [
        'updated_at'    => date('Y-m-d H:i:s'),
        'session_count' => $sessionCount,
        'markdown'      => $md
    ];
    file_put_contents($profileFile, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status'     => 'ok',
        'profile_md' => $md,
        'sessions'   => $sessionCount
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();
