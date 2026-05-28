<?php
/**
 * cron_gyakubari_note.php
 * 毎日自動で逆張りnote記事を生成し、メールで送信するスクリプト。
 * 実行完了後、配信状態ファイル state.json を更新して循環します。
 */

// タイムアウトを制限しない（LLM生成に時間がかかる場合があるため）
set_time_limit(0);

$root = __DIR__;
require_once $root . '/core/GeminiService.php';

$dataDir = $root . '/gyakubari_data/';
$markdownDir = $dataDir . 'markdown/';
$stateFile = $dataDir . 'state.json';
$instructionsFile = $dataDir . '勇さん逆張りnote執筆指示文.txt';
$indexFile = $dataDir . '勇さん個人note記事一覧.txt';
$articlesFile = $dataDir . 'Isamu_Personal_Note_Articles.md';
$logFile = $dataDir . 'automation_log.txt';

// ログ出力関数
function write_log($msg, $logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

write_log("Starting automated note generation process...", $logFile);

try {
    // 1. 各種ファイルの存在チェック
    if (!file_exists($stateFile)) {
        throw new Exception("State file missing: $stateFile");
    }
    if (!file_exists($instructionsFile)) {
        throw new Exception("Instructions file missing: $instructionsFile");
    }
    if (!file_exists($indexFile)) {
        throw new Exception("Index file missing: $indexFile");
    }

    // 2. エピソードファイルのリストアップとソート
    $files = [];
    if ($dh = opendir($markdownDir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file === '.' || $file === '..' || $file === '00_Radio_Map.md') continue;
            if (strpos($file, 'タイトル・概要') !== false) continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                $files[] = $file;
            }
        }
        closedir($dh);
    }

    if (empty($files)) {
        throw new Exception("No markdown episode files found in $markdownDir");
    }

    // 自然順ソート（シーズン-回 の数値順）
    usort($files, function($a, $b) {
        preg_match('/#(\d+)-(\d+)/', $a, $mA);
        preg_match('/#(\d+)-(\d+)/', $b, $mB);
        if (empty($mA) || empty($mB)) return strcasecmp($a, $b);
        $sA = (int)$mA[1];
        $eA = (int)$mA[2];
        $sB = (int)$mB[1];
        $eB = (int)$mB[2];
        if ($sA !== $sB) {
            return $sA <=> $sB;
        }
        return $eA <=> $eB;
    });

    // 3. state.json を読み込み、対象エピソードの決定
    $state = json_decode(file_get_contents($stateFile), true);
    if (!is_array($state)) {
        $state = ['current_episode' => '', 'history' => []];
    }

    $currentEpisode = $state['current_episode'] ?? '';
    $episodeIndex = array_search($currentEpisode, $files);

    if ($episodeIndex === false) {
        // 設定されていない、または見つからない場合はリストの最初にする
        $episodeIndex = 0;
        $currentEpisode = $files[0];
    }

    $targetFile = $markdownDir . $currentEpisode;
    if (!file_exists($targetFile)) {
        throw new Exception("Target episode file missing: $targetFile");
    }

    write_log("Target episode determined: $currentEpisode (Index: $episodeIndex)", $logFile);

    // 4. メイン文字起こし（整文）の読み込み
    $transcript = file_get_contents($targetFile);
    
    // エピソード記号（#1-1 などのプレフィックス）の抽出
    preg_match('/#(\d+)-(\d+)/', $currentEpisode, $matches);
    $episodeNum = $matches[0] ?? '#UNKNOWN';

    // 5. メタデータ（タイトル・概要）の読み込み（もしあれば）
    $metaContent = "";
    $metaFileName = $episodeNum . "タイトル・概要.md";
    // プレフィックス一致で探す（スペースやアンダーバーの有無を考慮）
    if ($dh = opendir($markdownDir)) {
        while (($file = readdir($dh)) !== false) {
            if (strpos($file, $episodeNum) !== false && strpos($file, 'タイトル・概要') !== false) {
                $metaContent = file_get_contents($markdownDir . $file);
                write_log("Associated title/meta file found: $file", $logFile);
                break;
            }
        }
        closedir($dh);
    }

    // 6. 執筆指示書の読み込み
    $instructions = file_get_contents($instructionsFile);

    // 7. 個人note記事一覧から最新記事と関連過去記事の抽出
    $indexLines = explode("\n", file_get_contents($indexFile));
    $articles = [];
    $latestArticle = null;

    foreach ($indexLines as $line) {
        // 例: "73. 成功哲学は幻想だ" のような見出し行をパース
        if (preg_match('/^(\d+)\.\s*(.*)$/', trim($line), $m)) {
            $id = (int)$m[1];
            $title = trim($m[2]);
            $articles[$id] = [
                'id' => $id,
                'title' => $title,
                'keywords' => [],
                'url' => 'https://note.com/mark136/n/n8e2ac3caecad' // デフォルト（インデックスからパースできない場合のフォールバック）
            ];
            // 直近でパースしたものを最新記事として保持（昇順で書かれている前提）
            if ($latestArticle === null || $id > $latestArticle['id']) {
                $latestArticle = &$articles[$id];
            }
        }
        // キーワード行のパース "キーワード: 成功哲学, 幻想, ..."
        if (preg_match('/^キーワード:\s*(.*)$/u', trim($line), $m) && isset($id)) {
            $kws = array_map('trim', explode(',', $m[1]));
            $articles[$id]['keywords'] = $kws;
        }
    }

    // 最新記事のURLの補正
    // Note_Articles.md からURLを特定する
    $articlesDetail = file_exists($articlesFile) ? file_get_contents($articlesFile) : '';
    if ($latestArticle !== null && !empty($articlesDetail)) {
        // 例: `[73. 成功哲学は幻想だ](https://note.com/mark136/n/n8e2ac3caecad)` のようなリンクパターンを探す
        $pattern = '/\[' . preg_quote($latestArticle['id'], '/') . '\.\s*' . preg_quote($latestArticle['title'], '/') . '\]\((https:\/\/note\.com\/mark136\/n\/[a-zA-Z0-9]+)\)/u';
        if (preg_match($pattern, $articlesDetail, $m)) {
            $latestArticle['url'] = $m[1];
        }
    }

    write_log("Latest article determined: No." . ($latestArticle['id'] ?? 'N/A') . " (" . ($latestArticle['title'] ?? 'N/A') . ")", $logFile);

    // 関連過去記事の選定（キーワードマッチング）
    $relatedArticle = null;
    $maxMatches = 0;
    
    // エピソードの文字起こしから単語をスキャンしてマッチング
    foreach ($articles as $id => $art) {
        if ($latestArticle !== null && $id === $latestArticle['id']) continue; // 最新記事は除外
        
        $matchesCount = 0;
        foreach ($art['keywords'] as $keyword) {
            if (empty($keyword)) continue;
            if (mb_strpos($transcript, $keyword) !== false) {
                $matchesCount++;
            }
        }

        if ($matchesCount > $maxMatches) {
            $maxMatches = $matchesCount;
            $relatedArticle = $art;
        }
    }

    // もしマッチするものがなければ、適当な代表過去記事をフォールバックとして使用（No.42など）
    if ($relatedArticle === null && isset($articles[42])) {
        $relatedArticle = $articles[42];
    } elseif ($relatedArticle === null && !empty($articles)) {
        // それでもなければ最後の要素の1つ前など
        $keys = array_keys($articles);
        $relatedArticle = $articles[$keys[count($keys) - 2]] ?? reset($articles);
    }

    // 関連過去記事のURLを特定
    if ($relatedArticle !== null && !empty($articlesDetail)) {
        $pattern = '/\[' . preg_quote($relatedArticle['id'], '/') . '\.\s*' . preg_quote($relatedArticle['title'], '/') . '\]\((https:\/\/note\.com\/mark136\/n\/[a-zA-Z0-9]+)\)/u';
        if (preg_match($pattern, $articlesDetail, $m)) {
            $relatedArticle['url'] = $m[1];
        } else {
            // URLが詳細ファイルから見つからない場合のフォールバック（代表URLなど、または予測）
            $relatedArticle['url'] = 'https://note.com/mark136/n/ndde695622033'; // No.42のURL
        }
    }

    write_log("Related past article selected: No." . ($relatedArticle['id'] ?? 'N/A') . " (" . ($relatedArticle['title'] ?? 'N/A') . ")", $logFile);

    // 8. 過去の生成履歴から被り防止の文脈を作成
    $historyForEpisode = [];
    foreach ($state['history'] as $hist) {
        if (($hist['episode'] ?? '') === $currentEpisode) {
            $historyForEpisode[] = $hist['title'] ?? '';
        }
    }

    $historyPrompt = "";
    if (!empty($historyForEpisode)) {
        $historyPrompt = "【重複回避の重要指示】\n"
                       . "このエピソードからは、過去に以下のタイトル・切り口の記事を生成しています：\n";
        foreach ($historyForEpisode as $pastTitle) {
            $historyPrompt .= "- 「" . $pastTitle . "」\n";
        }
        $historyPrompt .= "今回生成する記事が以前と完全に重複した内容や切り口にならないよう、今回はあえて別の違和感や、文字起こし内の異なる発言・エピソード（例：別の具体例や異なる問い）に焦点を当てて執筆してください。\n\n";
    }

    // 9. プロンプトの組み立て
    $latestUrl = $latestArticle ? $latestArticle['url'] : 'https://note.com/mark136/n/n8e2ac3caecad';
    $latestTitle = $latestArticle ? $latestArticle['title'] : '成功哲学は幻想だ';
    $relatedUrl = $relatedArticle ? $relatedArticle['url'] : 'https://note.com/mark136/n/ndde695622033';
    $relatedTitle = $relatedArticle ? $relatedArticle['title'] : '2人の死から学んだ人生観';

    $prompt = "あなたは「逆光」の勇さん（本名：イメ）として、以下の文字起こしと執筆指示書に基づき、高品質な逆張りnote記事本文を執筆してください。\n\n"
            . "【インプットデータ】\n"
            . "■ 対象エピソード: " . $currentEpisode . "\n"
            . "■ 文字起こし（整文）:\n" . $transcript . "\n\n"
            . ($metaContent ? "■ 参考情報（エピソードの概要・構成）:\n" . $metaContent . "\n\n" : "")
            . "■ 執筆ルール・指示書:\n" . $instructions . "\n\n"
            . $historyPrompt
            . "【記事内のリンクに関する重要指示】\n"
            . "1. 記事の前半（冒頭の挨拶ブロックの後）には、最新記事のリンクとして以下を自然に挿入してください。\n"
            . "   ・記事タイトル: " . $latestTitle . "\n"
            . "   ・URL: " . $latestUrl . "\n"
            . "2. 記事の後半（まとめセクションの直前）には、今回のテーマに関連する過去記事のリンクとして以下を自然に挿入してください。\n"
            . "   ・記事タイトル: " . $relatedTitle . "\n"
            . "   ・URL: " . $relatedUrl . "\n\n"
            . "【最終出力に関するルール】\n"
            . "・出力はMarkdown形式の記事本文のみとしてください。「以下は記事です」などの前置きは一切含めないでください。\n"
            . "・タイトルは必ずアスタリスク2つで囲んだ太字にしてください。タイトル内に「」『』【】などの記号を使用することは禁止です。\n"
            . "・見出しは必ず「###」を使用してください。\n"
            . "・本文中、「〜っていう」という表現は使用せず「〜という」で統一してください。\n"
            . "・段落と段落の間には必ず1行の空白行を挿入してください。\n"
            . "・まとめセクションの見出しは「### まとめ」とし、箇条書きの各行の間には必ず1行の空白行を挿入してください。\n"
            . "・末尾のCTAブロック（株式会社逆光のブランディング支援、Udatsuで整える“声の発信”、Podcastで“声の思考”を聴く）は、指示書に記載されているフォーマットを完全に再現してください。特にCTAの見出し（例: **▼株式会社逆光のブランディング支援**）は、直後に1行の空白行を入れ、URLの直前にも1行の空白行を入れてください。英語表記（of formatting等）を混ぜず、正しい日本語で出力してください。";

    write_log("Prompt constructed. Initiating Gemini text generation...", $logFile);

    // 10. Gemini APIでの生成実行
    $gemini = new GeminiService();
    $generatedText = trim($gemini->generateText($prompt, false));

    if (empty($generatedText)) {
        throw new Exception("Gemini generated content is empty.");
    }

    // 行頭や行末に余分なコードブロック記号 ``` があれば除去
    $generatedText = preg_replace('/^```(?:markdown)?\s*/i', '', $generatedText);
    $generatedText = preg_replace('/\s*```$/i', '', $generatedText);

    // 生成されたタイトルをパース
    $lines = explode("\n", $generatedText);
    $generatedTitle = "無題の記事";
    foreach ($lines as $line) {
        if (preg_match('/^\*\*(.*)\*\*$/', trim($line), $m)) {
            $generatedTitle = trim($m[1]);
            break;
        }
    }

    write_log("Gemini generation successful. Title: $generatedTitle", $logFile);

    // 11. メール送信の実行
    mb_language("Japanese");
    mb_internal_encoding("UTF-8");

    $to = "blb@nyct.jp";
    $subject = "【自動配信】今日の逆張りnote記事（" . $episodeNum . "）";
    $body = $generatedText;

    // ヘッダーのエンコード
    $fromName = mb_encode_mimeheader("逆張りマーケラジオ自動配信", "UTF-8", "B");
    $headers = "From: " . $fromName . " <noreply@udatsu-voyager.com>\r\n";
    $headers .= "Reply-To: blb@nyct.jp\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    write_log("Sending email to $to...", $logFile);
    $mailSuccess = mail($to, $subject, $body, $headers);

    if (!$mailSuccess) {
        throw new Exception("Email transmission failed in PHP mail().");
    }

    write_log("Email sent successfully to $to", $logFile);

    // 12. 配信状態の更新
    // 次のエピソードの特定
    $nextEpisodeIndex = $episodeIndex + 1;
    if ($nextEpisodeIndex >= count($files)) {
        $nextEpisodeIndex = 0; // 最新まで行ったら循環
        write_log("Reached the end of the episode list. Looping back to #1-1.", $logFile);
    }
    $nextEpisode = $files[$nextEpisodeIndex];

    // 履歴の追加
    $state['history'][] = [
        'date' => date('Y-m-d'),
        'episode' => $currentEpisode,
        'title' => $generatedTitle
    ];
    $state['current_episode'] = $nextEpisode;

    // state.json に保存
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    write_log("State updated. Next episode to generate: $nextEpisode", $logFile);

    echo json_encode(["status" => "success", "episode" => $currentEpisode, "title" => $generatedTitle]);

} catch (Throwable $e) {
    write_log("CRITICAL ERROR: " . $e->getMessage(), $logFile);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit(1);
}
