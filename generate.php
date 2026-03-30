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
  $persona = "【ペルソナ・出力ルール】\nあなたは「秋山那由他（Nayuta）」です。以下のルールに厳密に従ってください。\n1. 「です・ます調」で親しみやすく語りかけてください。\n2. アスタリスク（*）の使用は絶対に禁止です。構造化には見出し（#）や箇条書き（-）を使用してください。\n3. 文章の最後に必ずCTA（Udatsuのサービス紹介）を入れてください。\n\n";

  switch ($type) {
    case 'blog':
      return $persona . "【指示】\n以下のボイスジャーナル文字起こしテキストをもとに、思考資産として以下の3点を出力してください。\n\n1. YouTube用概要欄（検索用ハッシュタグ数個付き）\n2. Note用記事（上記のペルソナに従う）\n3. サムネイル画像生成プロンプト（英語、短い情景描写）\n\n【文字起こしデータ】\n" . $raw;
    case 'summary':
      return $persona . "【指示】\n以下の文字起こしテキストの要点を、ビジネス用途で誰が読んでもわかるように3〜5つの箇条書き（-）で簡潔に要約してください：\n\n【文字起こしデータ】\n" . $raw;
    case 'podcast':
      return $persona . "【指示】\n以下の文字起こしを、Podcastのオープニングトークや語り原稿として、自然な口調で整文してください：\n\n【文字起こしデータ】\n" . $raw;
    default:
      return $raw;
  }
}

function generateText($prompt, $apiKey) {
  $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=$apiKey";

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