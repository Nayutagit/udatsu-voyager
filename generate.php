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

// 🔸 Gemini APIキー読み込み
require_once __DIR__ . '/config/gemini_key.php';
$apiKey = $geminiApiKey;

function buildPrompt($type, $raw) {
  switch ($type) {
    case 'blog':
      return "以下の音声文字起こしを『ブログ・note用』に整文してください。読んだ人が真似したくなる構成で、小見出しには「・」を使い、親しみのある語り口で書いてください。\n\n" . $raw;
    case 'summary':
      return "以下の内容をビジネス用途で誰が読んでもわかるように簡潔に要約してください：\n\n" . $raw;
    case 'podcast':
      return "以下の文字起こしをPodcastの原稿として自然に話す口調で整文してください：\n\n" . $raw;
    default:
      return $raw;
  }
}

function generateText($prompt, $apiKey) {
  $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=$apiKey";

  $postData = [
    "contents" => [
      [
        "parts" => [
          ["text" => $prompt]
        ]
      ]
    ],
    "generationConfig" => [
        "temperature" => 0.7
    ]
  ];

  $headers = [
    "Content-Type: application/json"
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
  $response = curl_exec($ch);
  
  if (curl_errno($ch)) {
      return json_encode(["error" => "Curl error: " . curl_error($ch)]);
  }
  curl_close($ch);

  $data = json_decode($response, true);

  if (isset($data['error'])) {
    return json_encode(["error" => $data['error']['message'] ?? 'Unknown Gemini API Error']);
  }

  return $data['candidates'][0]['content']['parts'][0]['text'] ?? "整文エラーが発生しました（Gemini）。";
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