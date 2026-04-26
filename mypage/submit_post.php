<?php
include_once __DIR__ . '/../config/plan_limits.php';

session_start();

$uid  = $_SESSION['uid'] ?? '';
$plan = $_SESSION['user_plan'] ?? 'trial';

if (!$uid) {
  header("Location: ../index.php");
  exit();
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

$index  = $_POST['index'] ?? null;
$action = $_POST['action'] ?? '';

if (!is_numeric($index)) {
  header("Location: dashboard.php");
  exit();
}

$posts = file_exists($postsFile)
  ? json_decode(file_get_contents($postsFile), true)
  : [];

if (!isset($posts[$index])) {
  header("Location: dashboard.php");
  exit();
}

// OpenAIを使って整文・要約・キーワードを取得
// Geminiを使って整文・要約・キーワードを取得
function callGeminiForRefinement(string $originalText): array {
  require __DIR__ . '/../config/gemini_key.php';
  $apiKey = $geminiApiKey;

  $prompt = "以下の文章を整文して、思考資産として保存できるように構造化してください。\n\n"
          . "【原文】\n$originalText\n\n"
          . "出力形式はJSONで以下のようにお願いします：\n"
          . "{\n"
          . "  \"refined_text\": \"...\",\n"
          . "  \"summary\": \"...\",\n"
          . "  \"keywords\": [\"...\", \"...\", \"...\"]\n"
          . "}";

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
        "temperature" => 0.7,
        "response_mime_type" => "application/json"
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
  
  $result = curl_exec($ch);

  if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    return [
      'refined_text' => '（整文取得失敗：' . $error_msg . '）',
      'summary' => '(要約解析失敗)',
      'keywords' => []
    ];
  }

  curl_close($ch);

  $json = json_decode($result, true);
  $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
  
  $data = json_decode($text, true);

  return is_array($data) ? $data : [
    'refined_text' => $text,
    'summary' => '(要約解析失敗)',
    'keywords' => []
  ];
}

switch ($action) {
  case 'stack':
    // original_textの候補を複数見て優先順位で選ぶ
    $original = '';
    foreach (['original_text', 'content', 'refined_text', 'title'] as $key) {
      if (!empty($posts[$index][$key])) {
        $original = $posts[$index][$key];
        break;
      }
    }
    if (trim($original) === '') {
      $original = '（原文なし）';
    }

    $posts[$index]['status'] = 'My Udastack追加済';
    $posts[$index]['date'] = date('Y-m-d');

    $result = callGeminiForRefinement($original);
    $posts[$index]['refined_text'] = $result['refined_text'] ?? '';
    $posts[$index]['summary']      = $result['summary'] ?? '';
    $posts[$index]['keywords']     = $result['keywords'] ?? [];
    break;

  case 'unstack':
    $posts[$index]['status'] = '';
    break;

  case 'delete':
    $posts[$index]['status'] = '削除済';
    $posts[$index]['deleted_at'] = date('Y-m-d');
    break;

  case 'share':
    $wasSharedBefore = !empty($posts[$index]['is_shared']);
    $posts[$index]['is_shared'] = 1;
    
    if (!$wasSharedBefore) {
        $title = $posts[$index]['title'] ?? '';
        $myFollowingFile = $userDir . $uid . '_following.json';
        $myFollowersFile = $userDir . $uid . '_followers.json';
        $lineMapFile = $userDir . 'line_map.json';
        
        $myFollowing = file_exists($myFollowingFile) ? json_decode(file_get_contents($myFollowingFile), true) ?: [] : [];
        $myFollowers = file_exists($myFollowersFile) ? json_decode(file_get_contents($myFollowersFile), true) ?: [] : [];
        $mutualUids = array_intersect($myFollowing, $myFollowers);
        
        if (!empty($mutualUids)) {
            $lineMap = file_exists($lineMapFile) ? json_decode(file_get_contents($lineMapFile), true) ?: [] : [];
            $uidToLineId = array_flip($lineMap); // Map UID => LineUserId
            
            $profileFile = $userDir . $uid . '_profile.json';
            $myProfile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
            $myName = $myProfile['display_name'] ?? 'あなたと相互フォローのユーザー';
            
            $pushMessage = "📢 {$myName}さんが新しい音声をタイムラインに共有しました！\n\nタイトル：{$title}\n\n▼タイムラインを開いて確認する\nhttps://udatsu-voyager.com/mypage/timeline.php?openExternalBrowser=1";
            
            $lineConfig = file_exists(__DIR__ . '/../config/line_config.php') ? require __DIR__ . '/../config/line_config.php' : null;
            if ($lineConfig && !empty($lineConfig['channel_access_token'])) {
                $accessToken = $lineConfig['channel_access_token'];
                foreach ($mutualUids as $mutualUid) {
                    if (isset($uidToLineId[$mutualUid])) {
                        $targetLineId = $uidToLineId[$mutualUid];
                        $url = 'https://api.line.me/v2/bot/message/push';
                        $postData = ['to' => $targetLineId, 'messages' => [['type' => 'text', 'text' => $pushMessage]]];
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=UTF-8', 'Authorization: Bearer ' . $accessToken]);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
        }
    }
    break;

  case 'unshare':
    $posts[$index]['is_shared'] = 0;
    break;

  default:
    header("Location: dashboard.php");
    exit();
}

file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
  echo json_encode(['status' => 'ok']);
  exit();
}

header("Location: dashboard.php");
exit();