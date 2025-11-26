<?php
session_start();

// 🔸 入力チェック
$type = $_POST['type'] ?? '';
$raw = $_POST['raw'] ?? '';
$uid = $_SESSION['uid'] ?? '';
$plan = $_SESSION['user_plan'] ?? 'guest';

if (!$type || !$raw) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid input"]);
  exit();
}

// 🔸 OpenAI APIキー
$apiKey = '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';  // ★ご自身のキーに差し替えてください

function buildPrompt($type, $raw) {
  switch ($type) {
    case 'blog':
      return "以下の音声文字起こしを『ブログ・note用』に整文してください。読んだ人が真似したくなる構成で、小見出しには「・」を使い、親しみのある語り口で書いてください。

" . $raw;
    case 'summary':
      return "以下の内容をビジネス用途で誰が読んでもわかるように簡潔に要約してください：

" . $raw;
    case 'podcast':
      return "以下の文字起こしをPodcastの原稿として自然に話す口調で整文してください：

" . $raw;
    default:
      return $raw;
  }
}

function generateText($prompt, $apiKey) {
  $postData = [
    "model" => "gpt-4o",
    "messages" => [
      ["role" => "system", "content" => "あなたはプロの編集者です。"],
      ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.7
  ];

  $headers = [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
  ];

  $ch = curl_init("https://api.openai.com/v1/chat/completions");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
  $response = curl_exec($ch);
  curl_close($ch);

  $data = json_decode($response, true);

  if (isset($data['error'])) {
    return json_encode(["error" => $data['error']]);
  }

  return $data['choices'][0]['message']['content'] ?? "整文エラーが発生しました。";
}

// 🔸 実行
$prompt = buildPrompt($type, $raw);
$fullText = generateText($prompt, $apiKey);

// 🔸 ゲストは半分表示＋残りは result.php で制御
if ($plan === 'guest') {
  $limit = floor(mb_strlen($fullText) * 0.5);
  $visible = mb_substr($fullText, 0, $limit);
  echo $visible;
  exit();
}

// 🔸 通常表示
echo $fullText;
exit();
?>