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
      return "以下のボイスジャーナル文字起こしテキストをもとに、思考資産として以下の3点を出力してください。\n\n1. YouTube用概要欄（検索用ハッシュタグ数個付き）\n2. Note用記事\n3. サムネイル画像生成プロンプト（英語、短い情景描写）\n\n【文字起こしデータ】\n" . $raw;
    case 'summary':
      return "以下の文字起こしテキストの要点を、ビジネス用途で誰が読んでもわかるように3〜5つの箇条書き（-）で簡潔に要約してください：\n\n【文字起こしデータ】\n" . $raw;
    case 'podcast':
      return "以下の文字起こしを、Podcastのオープニングトークや語り原稿として、自然な口調で整文してください：\n\n【文字起こしデータ】\n" . $raw;
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

  $maxRetries = 3;
  $attempt = 0;

  while ($attempt < $maxRetries) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
      curl_close($ch);
    
      if ($curlError) {
          return "⚠ 通信エラーが発生しました: " . $curlError;
      }
    
      $data = json_decode($response, true);
    
      if ($httpCode >= 200 && $httpCode < 300 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
          return $data['candidates'][0]['content']['parts'][0]['text'];
      }
    
      if (isset($data['error'])) {
        $errorMsg = $data['error']['message'] ?? 'Unknown Gemini API Error';
        if ($httpCode >= 500 || strpos(strtolower($errorMsg), 'demand') !== false || strpos(strtolower($errorMsg), 'quota') !== false) {
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(2 * $attempt);
                continue;
            }
            return "⚠ AIサーバーが混雑しています（High Demand）。\n時間をおいてから再度「Generate Asset」ボタンを押してください。\n\n[詳細: $errorMsg]";
        }
        return "⚠ APIエラー: " . $errorMsg;
      }
      
      return "⚠ 予期せぬエラーが発生しました。";
  }
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