<?php
/**
 * api_chat.php
 * ボイスジャーナル(VJ)を解析し、ログインユーザーのAI分身としてチャット応答を生成するAPI。
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

// VJテキストの集約
$vjContext = "";
$count = 1;
foreach ($validPosts as $p) {
    $title = $p['title'] ?? '無題';
    $date = $p['date'] ?? '';
    $category = $p['category'] ?? 'ボイスジャーナル';
    // 整文を優先
    $text = !empty($p['refined_text']) ? $p['refined_text'] : ($p['text'] ?? $p['original_text'] ?? '');
    $summary = $p['summary'] ?? '';
    
    if (empty($text) && empty($summary)) continue;
    
    $vjContext .= "【ジャーナル #{$count}】\n";
    $vjContext .= "日付: {$date}\n";
    $vjContext .= "タイトル: {$title}\n";
    $vjContext .= "カテゴリ: {$category}\n";
    if (!empty($summary)) {
        $vjContext .= "要約: {$summary}\n";
    }
    if (!empty($text)) {
        $vjContext .= "内容: " . mb_strimwidth($text, 0, 1000, '...') . "\n";
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
$prompt .= "分身AI: ";

try {
    $gemini = new GeminiService();
    // VJデータを含むため、コンテキストは中サイズ。gemini-flash-latest を使用
    $reply = $gemini->generateText($prompt, false);
    $reply = trim($reply);
    
    echo json_encode([
        'status' => 'ok',
        'reply' => $reply
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'AIの応答生成中にエラーが発生しました: ' . $e->getMessage()
    ]);
}
exit;
