<?php
/**
 * generate_quiz.php
 * Uses Gemini to generate 10 personalized A/B scenario questions
 * based on the user's VJ posts + previous quiz answers.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'questions' => []]);
    exit();
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$sessFile  = $userDir . $uid . '_quiz_sessions.json';

// Get user's VJ data (max 30 recent posts)
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
$validPosts = array_filter($posts ?? [], fn($p) => !in_array($p['status'] ?? '', ['解析中', 'エラー', '削除済']));
$validPosts = array_slice(array_reverse(array_values($validPosts)), 0, 30);

$vjSummary = '';
foreach ($validPosts as $p) {
    $text = mb_strimwidth($p['original_text'] ?? $p['text'] ?? '', 0, 500);
    if ($text) $vjSummary .= "【{$p['title']}】\n{$text}\n\n";
}
$vjSummary = mb_strimwidth($vjSummary, 0, 8000);

// Get previous quiz sessions (last 5) to avoid repetition and increase depth
$sessions = file_exists($sessFile) ? json_decode(file_get_contents($sessFile), true) : [];
$recentSessions = array_slice($sessions ?? [], -5);
$prevAnswerSummary = '';
foreach ($recentSessions as $session) {
    foreach ($session['answers'] ?? [] as $ans) {
        $prevAnswerSummary .= "Q: {$ans['question']} → {$ans['chosen_text']}\n";
    }
}

$hasPrevAnswers = !empty($prevAnswerSummary);
$prevContext = $hasPrevAnswers
    ? "\n\n【過去の回答パターン（参考にして、より深い・違う角度からの質問を生成してください）】\n{$prevAnswerSummary}"
    : '';

$vjContext = $vjSummary
    ? "\n\n【ユーザーの思考メモ（VJデータ）】\n{$vjSummary}"
    : '';

$prompt = <<<EOT
あなたはユーザーの思考・価値観・行動パターンを深く分析するための質問を設計する専門家です。

以下の情報をもとに、ユーザーの思考傾向を明らかにするための「シナリオ型A/Bクイズ」を10問作成してください。{$vjContext}{$prevContext}

【ルール】
- 各質問は「具体的なシナリオ（状況説明）」と「どちらの行動をとりますか？という問い」と「選択肢A・B」の3要素で構成してください
- 選択肢は「どちらが正解かわからない」ものにしてください（正誤ではなく価値観・優先順位の違いを問う）
- ビジネス・人間関係・学習・リスク・表現・時間の使い方など、多様なテーマをカバーしてください
- ユーザーのVJデータに関連するテーマを優先してください（VJデータがある場合）
- 過去の回答と重複しないようにしてください

【出力形式】
以下のJSON配列のみを返してください（Markdownのコードブロックは不要）:
[
  {
    "scenario": "〜という状況のとき",
    "question": "あなたはどちらの行動をとりますか？",
    "a": "Aの行動内容",
    "b": "Bの行動内容"
  }
]
EOT;

try {
    $gemini  = new GeminiService();
    $raw     = $gemini->generateText($prompt, true);
    
    // Strip markdown code fences if present
    $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
    $raw = preg_replace('/^```\s*/m', '', $raw);
    $raw = trim($raw);
    
    $questions = json_decode($raw, true);
    if (!is_array($questions) || count($questions) === 0) {
        throw new Exception('Invalid JSON from Gemini: ' . $raw);
    }
    
    echo json_encode(['status' => 'ok', 'questions' => array_slice($questions, 0, 10)]);
} catch (Exception $e) {
    // Fallback: return generic questions
    echo json_encode([
        'status' => 'fallback',
        'questions' => [
            ['scenario' => '新しいビジネスアイデアが浮かんだとき', 'question' => 'あなたはどちらの行動をとりますか？', 'a' => 'まず周囲に話して意見をもらう', 'b' => '自分でリサーチして確信が持てるまで動かない'],
            ['scenario' => '重要なプレゼンの前日', 'question' => 'あなたはどちらを優先しますか？', 'a' => '準備の完成度を高めるために夜遅くまで作業する', 'b' => '早めに休んでコンディションを整える'],
            ['scenario' => '予定外のトラブルが発生したとき', 'question' => 'あなたはどう対応しますか？', 'a' => 'まず状況を整理して計画を立て直す', 'b' => 'とりあえず動いてみて修正していく'],
            ['scenario' => '信頼できる友人から反対意見をもらったとき', 'question' => 'あなたはどう動きますか？', 'a' => '意見を取り入れて計画を見直す', 'b' => '自分の判断を信じてそのまま進む'],
            ['scenario' => '忙しい時期に新しい学習機会が現れたとき', 'question' => '優先することはどちらですか？', 'a' => '今の仕事に集中して学習は後回し', 'b' => '多少無理をしても学習の機会を掴む'],
            ['scenario' => '大きな成果を出したとき', 'question' => 'あなたはどちらを選びますか？', 'a' => '周囲に積極的にアピールする', 'b' => '自分の中で静かに喜びを噛み締める'],
            ['scenario' => 'チームの仕事でミスが発生したとき', 'question' => '最初に取る行動はどちらですか？', 'a' => 'まず原因分析を徹底して再発防止策を考える', 'b' => '影響を受ける人たちへの謝罪と対応を最優先する'],
            ['scenario' => '2つの仕事を同時に抱えているとき', 'question' => 'あなたはどちらの方法を選びますか？', 'a' => '一つを完璧に仕上げてから次に移る', 'b' => '両方を並行して少しずつ進める'],
            ['scenario' => '面白いアイデアを思いついたとき', 'question' => '最初に何をしますか？', 'a' => 'すぐにメモして誰かに話す', 'b' => '頭の中で十分に練り上げてから行動する'],
            ['scenario' => '自分の専門外の問題を任されたとき', 'question' => 'あなたはどうしますか？', 'a' => '専門家に相談しながら進める', 'b' => '自分で調べて解決する方法を探す'],
        ]
    ]);
}
exit();
