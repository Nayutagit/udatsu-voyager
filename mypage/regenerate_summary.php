<?php
require_once __DIR__ . '/../core/bootstrap.php';

$apiKey = include __DIR__ . '/../config/openai_key.php';
header('Content-Type: application/json');

// セッション確認
if (empty($uid)) {
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

// POSTから指示文を受け取る
$input = json_decode(file_get_contents('php://input'), true);
$promptInput = trim($input['prompt'] ?? '');

if (!$promptInput) {
  echo json_encode(['error' => 'プロンプトが空です']);
  exit;
}

// 投稿データを取得
$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$stacked = array_filter($posts, fn($p) => $p['status'] === 'My Udastack追加済');
$refinedTexts = array_column($stacked, 'refined_text');

$joined = implode("\n\n---\n\n", array_filter($refinedTexts));

$finalPrompt = <<<PROMPT
以下はユーザーが積み上げた思考資産（整文済）です。これをもとに、全体の要約を構造化しながら再生成してください。特に以下のユーザー指示を尊重してください：

【ユーザーの指示】
{$promptInput}

【思考資産一覧】
{$joined}

# 出力形式
・200〜600文字の日本語で構造的な要約
・文体はやわらかく、自己内省っぽさを保つ
・PREAP構成を軽く意識（主張→理由→具体→反論理解→再主張）
PROMPT;

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer $apiKey"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => 'gpt-4',
  'messages' => [
    ['role' => 'system', 'content' => 'あなたは思考整理の専門家であり、やわらかくも構造的に要約するアシスタントです。'],
    ['role' => 'user', 'content' => $finalPrompt]
  ],
  'temperature' => 0.7,
]));

$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$summary = $result['choices'][0]['message']['content'] ?? '';

echo json_encode(['summary' => trim($summary)]);