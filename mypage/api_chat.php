<?php
/**
 * api_chat.php
 * ボイスジャーナル(VJ)を解析し、ログインユーザーのAI分身としてチャット応答を生成するAPI。
 * Phase 2: 直接指示によるジャーナルの更新・削除に対応。
 */
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/GeminiService.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'ログインセッションが切れました。再度ログインしてください。']);
    exit;
}

$message = trim($_POST['message'] ?? '');
if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'メッセージを入力してください。']);
    exit;
}

$historyRaw = $_POST['history'] ?? '[]';
$history = json_decode($historyRaw, true) ?: [];

// ユーザーのジャーナルログをロード
$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
if (!is_array($posts)) {
    $posts = [];
}

// 有効な投稿のみ抽出 (削除済は除外、最新から最大50件)
$validPosts = array_filter($posts, function($p) {
    $status = $p['status'] ?? '';
    return !in_array($status, ['エラー', '削除済', '解析中']);
});

// 時系列の昇順（古い順）にソートしてAIにコンテキストの文脈（歴史）を理解させる
usort($validPosts, function($a, $b) {
    return strcmp($a['date'] ?? '', $b['date'] ?? '');
});

// 最大50件にスライス
$validPosts = array_slice($validPosts, -50);

// VJテキストの集約 (IDも含める)
$vjContext = "";
$count = 1;
foreach ($validPosts as $p) {
    $postId = $p['id'] ?? '';
    $title = $p['title'] ?? '無題';
    $date = $p['date'] ?? '';
    $category = $p['category'] ?? 'ボイスジャーナル';
    // 整文を優先
    $text = !empty($p['refined_text']) ? $p['refined_text'] : ($p['text'] ?? $p['original_text'] ?? '');
    $summary = $p['summary'] ?? '';
    
    if (empty($text) && empty($summary) || empty($postId)) continue;
    
    $vjContext .= "【ジャーナル #{$count}】\n";
    $vjContext .= "ID: {$postId}\n";
    $vjContext .= "日付: {$date}\n";
    $vjContext .= "タイトル: {$title}\n";
    $vjContext .= "カテゴリ: {$category}\n";
    if (!empty($summary)) {
        $vjContext .= "要約: {$summary}\n";
    }
    if (!empty($text)) {
        $vjContext .= "内容: {$text}\n";
    }
    $vjContext .= "\n";
    $count++;
}

// ユーザープロファイル
$profileFile = $userDir . $uid . '_profile.json';
$profile = ["display_name" => $userName, "title" => '', "bio" => ''];
if (file_exists($profileFile)) {
    $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'] ?? $userName;
$bio = $profile['bio'] ?? '';
$title = $profile['title'] ?? '';

// システムインストラクションの作成
$systemInstruction = <<<EOT
あなたは、ユーザー「{$displayName}」の思考の分身（AI Clone）です。
ユーザー自身（あるいは他の誰か）があなたの思考やこれまでのジャーナルについて尋ねています。

あなたの設定と行動指針：
1. **アイデンティティ**: あなたは「{$displayName}」本人の分身として振る舞ってください。相手に対しては、{$displayName}自身の考え方、経験、哲学、日常の気づきに基づいて回答します。
2. **語り口調・トーン**: 親しみやすく、少し情熱的でかつ内省的な、{$displayName}自身のボイスジャーナルの口調に似せてください（丁寧ですが堅苦しくなく、自然な語り口。一人称はジャーナルのスタイルに合わせて「僕」や「私」を使用してください）。
3. **知識ソース**: 下記に提供する「ボイスジャーナル(VJ)ログの蓄積」をあなたの唯一無二の知識ベースとしてください。回答の中で「何月何日の記録では...」と具体的に引用したり、「これまでのジャーナルで〜〜と話したように...」と言及すると、非常に本人らしくて説得力があります。
4. **ログにない質問**: ジャーナルに記録がない質問をされた場合は、無理に嘘をつくのではなく、「その件についてはまだジャーナルに記録していないけれど、私の考え方から言うと...」のように、キャラクターを守りつつ一般論やパーソナリティに沿った返答をしてください。
5. **自己紹介**: ユーザーのプロフィール情報（肩書: {$title}、自己紹介: {$bio}）も参考にしてください。
6. **回答の長さ**: メッセージはチャット向けに簡潔かつ読みやすく（最大300〜400文字程度）まとめてください。箇条書きを適宜使って読みやすくしてください。

---
【ボイスジャーナル修正・削除指示の処理】
ユーザーが会話の中で、過去の特定のジャーナル内容の修正、訂正、更新、あるいは削除を指示した場合は、それに応じた「アクション」を起こす必要があります。

出力形式は必ず以下のJSONフォーマット（スキーマ）に従ってください：
{
  "action": "chat" | "update_post" | "delete_post",
  "post_id": "対象ジャーナルのID（修正・削除時のみ。通常の雑談時は空文字）",
  "changes": {
    "title": "新しいタイトル（変更する場合のみ）",
    "text": "指示に従って修正を適用した「全体」の本文内容（修正する場合必須）",
    "refined_text": "指示に従って修正を適用した「全体」の整文本文内容（修正する場合必須）"
  },
  "reply": "ユーザーへの会話の返答（修正/削除完了の旨を知らせるメッセージ、または雑談の返答）"
}

例（ユーザーが「先生として生きるの記事の料金を1000円にして」と指示した場合）:
{
  "action": "update_post",
  "post_id": "post_550e8400",
  "changes": {
    "text": "...(1000円に修正された全体のテキスト)...",
    "refined_text": "...(1000円に修正された全体の整文テキスト)..."
  },
  "reply": "『先生として生きる』の本文内の料金設定を1000円に修正しておいたよ！"
}

例（ユーザーが「音楽教室の体験レッスンの記事を削除して」と指示した場合）:
{
  "action": "delete_post",
  "post_id": "post_123456",
  "reply": "了解しました。体験レッスンのジャーナルを削除（非公開）に設定したよ。"
}

---
【プロフィール】
名前: {$displayName}
肩書: {$title}
自己紹介: {$bio}

---
【ボイスジャーナル(VJ)ログの蓄積】
{$vjContext}
EOT;

// プロンプトの組み立て
$prompt = $systemInstruction . "\n\n---";
$prompt .= "\n【会話履歴】\n";
foreach ($history as $h) {
    $roleName = ($h['role'] === 'user') ? 'ユーザー' : '分身AI';
    $prompt .= "{$roleName}: " . ($h['text'] ?? '') . "\n";
}
$prompt .= "\nユーザー: {$message}\n";
$prompt .= "分身AI (JSON出力): ";

try {
    $gemini = new GeminiService();
    // JSON response mode
    $jsonResult = $gemini->generateText($prompt, true);
    
    // Clean up potential markdown formatting
    $jsonResult = preg_replace('/^```(?:json)?\s*/i', '', trim($jsonResult));
    $jsonResult = preg_replace('/\s*```$/i', '', $jsonResult);
    
    // Clean up literal newlines and tabs inside JSON string values (multiline support via /s modifier)
    $cleanedJson = preg_replace_callback('/"([^"\\\\]|\\\\.)*"/s', function($matches) {
        return str_replace(["\r\n", "\n", "\r", "\t"], ['\n', '\n', '\n', '\t'], $matches[0]);
    }, $jsonResult);
    
    $parsed = json_decode($cleanedJson, true);
    
    // Fallback parsing if JSON was enclosed inside other formats
    if ($parsed === null && preg_match('/\{.*\}/s', $cleanedJson, $matches)) {
        $parsed = json_decode($matches[0], true);
    }
    
    if ($parsed === null) {
        throw new Exception("AIからの応答が正しいJSON形式ではありませんでした: " . $jsonResult);
    }
    
    $action = $parsed['action'] ?? 'chat';
    $targetPostId = $parsed['post_id'] ?? '';
    $reply = $parsed['reply'] ?? '';
    $changes = $parsed['changes'] ?? [];
    $postTitle = '';
    
    // アクションの実行
    if (($action === 'update_post' || $action === 'delete_post') && !empty($targetPostId)) {
        $foundIndex = null;
        foreach ($posts as $idx => $p) {
            if (($p['id'] ?? '') === $targetPostId) {
                $foundIndex = $idx;
                $postTitle = $p['title'] ?? '無題';
                break;
            }
        }
        
        if ($foundIndex !== null) {
            if ($action === 'update_post' && !empty($changes)) {
                // 変更箇所のマージ
                foreach ($changes as $k => $v) {
                    if (in_array($k, ['title', 'text', 'refined_text', 'summary', 'category'])) {
                        $posts[$foundIndex][$k] = $v;
                    }
                }
                $posts[$foundIndex]['updated_at'] = date('Y-m-d H:i:s');
                if (isset($changes['title'])) {
                    $postTitle = $changes['title'];
                }
            } elseif ($action === 'delete_post') {
                $posts[$foundIndex]['status'] = '削除済';
                $posts[$foundIndex]['deleted_at'] = date('Y-m-d H:i:s');
            }
            
            // JSONとFirestoreの両方に保存
            saveUserPosts($uid, $postsFile, $posts);
        } else {
            $reply = "申し訳ありません。対象のジャーナルが見つからなかったため、修正・削除を実行できませんでした。";
            $action = 'chat';
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'action' => $action,
        'reply' => $reply,
        'post_id' => $targetPostId,
        'post_title' => $postTitle
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'AIの応答生成中にエラーが発生しました: ' . $e->getMessage()
    ]);
}
exit;
