<?php
include_once __DIR__ . '/../config/plan_limits.php';
require_once __DIR__ . '/../core/GeminiService.php';

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
  header("Location: mypage.php");
  exit();
}

$posts = file_exists($postsFile)
  ? json_decode(file_get_contents($postsFile), true)
  : [];

if (!isset($posts[$index])) {
  header("Location: mypage.php");
  exit();
}

// OpenAIを使って整文・要約・キーワードを取得
// Geminiを使って整文・要約・キーワードを取得
function callGeminiForRefinement(string $originalText): array {
  $prompt = "以下の文章を整文して、思考資産として保存できるように構造化してください。\n"
          . "【重要指示】記述は必ず一人称（自分視点）で行ってください。「ユーザーは〜」ではなく「私は〜」「〜だと考えた」といった、本人の独白・思考ログとして整理してください。\n\n"
          . "【原文】\n$originalText\n\n"
          . "出力形式はJSONで以下のようにお願いします：\n"
          . "{\n"
          . "  \"refined_text\": \"...\",\n"
          . "  \"summary\": \"...\",\n"
          . "  \"keywords\": [\"...\", \"...\", \"...\"]\n"
          . "}";

  try {
    $gemini = new GeminiService();
    $text = $gemini->generateText($prompt, true);
    
    // Clean up potential markdown formatting if Gemini didn't return pure JSON
    $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/i', '', $text);
    
    $data = json_decode($text, true);
    
    // If json_decode fails, try harder to find JSON within the text
    if ($data === null && preg_match('/\{.*\}/s', $text, $matches)) {
        $data = json_decode($matches[0], true);
    }

    if (is_array($data)) {
        return $data;
    }
  } catch (Exception $e) {
    // Fallback on error
  }

  return [
    'refined_text' => $originalText,
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
    $posts[$index]['is_shared'] = 0; // 削除時は共有もオフにする
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
        
        $myFollowing = file_exists($myFollowingFile) ? (json_decode(file_get_contents($myFollowingFile), true) ?: []) : [];
        $myFollowers = file_exists($myFollowersFile) ? (json_decode(file_get_contents($myFollowersFile), true) ?: []) : [];
        $mutualUids = array_intersect($myFollowing, $myFollowers);
        
        if (!empty($mutualUids)) {
            $lineMap = file_exists($lineMapFile) ? (json_decode(file_get_contents($lineMapFile), true) ?: []) : [];
            $uidToLineId = array_flip($lineMap); // Map UID => LineUserId
            
            $profileFile = $userDir . $uid . '_profile.json';
            $myProfile = file_exists($profileFile) ? (json_decode(file_get_contents($profileFile), true) ?: []) : [];
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

  case 'update_date':
    $newDate = $_POST['date'] ?? '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
        $posts[$index]['date'] = $newDate;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
        exit();
    }
    break;

  default:
    header("Location: mypage.php");
    exit();
}

file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
  echo json_encode(['status' => 'ok']);
  exit();
}

header("Location: mypage.php");
exit();