<?php
// gyakubari_copy.php
// Note向けコピペ専用ツール（装飾維持）

$dataDir = __DIR__ . '/gyakubari_data/';
$stateFile = $dataDir . 'state.json';
$archiveDir = $dataDir . 'archive/';

$ep = $_GET['ep'] ?? '';

// ep の指定がない場合は、state.json の最新履歴から取得
if (empty($ep) && file_exists($stateFile)) {
    $state = json_decode(file_get_contents($stateFile), true);
    if (!empty($state['history'])) {
        $last = end($state['history']);
        // #1-1ブランディングとは.md からプレフィックスを取得
        $ep = preg_replace('/\.md$/i', '', $last['episode'] ?? '');
    }
}

$articleText = '';
$articleHtml = '';
$error = '';

if (empty($ep)) {
    $error = 'エピソードが指定されていません。';
} else {
    $filePath = $archiveDir . $ep . '.txt';
    if (file_exists($filePath)) {
        $articleText = file_get_contents($filePath);
        
        // MarkdownからHTMLへの簡易パース
        $html = htmlspecialchars($articleText, ENT_QUOTES, 'UTF-8');
        
        // 太字の置換
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        
        // 見出しの置換 (###)
        $html = preg_replace('/^###\s*(.*?)$/m', '<h3>$1</h3>', $html);
        
        // 段落と改行の整理
        $lines = explode("\n", $html);
        $parsedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '<h3>') === 0) {
                $parsedLines[] = $trimmed;
            } elseif (empty($trimmed)) {
                $parsedLines[] = '<p><br></p>';
            } else {
                $parsedLines[] = '<p>' . $line . '</p>';
            }
        }
        $articleHtml = implode("\n", $parsedLines);
    } else {
        $error = '指定されたエピソード（' . htmlspecialchars($ep) . '）のアーカイブ記事が見つかりません。自動生成がまだ実行されていない可能性があります。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noteコピーツール | Udatsu Voyager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Outfit:wght@400;600&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #0b0c10;
            --card-bg: #1f2833;
            --accent-color: #66fcf1;
            --accent-hover: #45f3e5;
            --text-color: #c5c6c7;
            --text-title: #ffffff;
            --border-color: #45f3e5;
            --primary-grad: linear-gradient(135deg, #0575e6 0%, #00f260 100%);
        }

        body {
            font-family: 'Inter', 'Noto Sans JP', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        .container {
            max-width: 800px;
            width: 90%;
            margin: 40px auto;
            padding: 20px;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
        }

        header img {
            height: 40px;
            margin-bottom: 10px;
        }

        header h1 {
            font-family: 'Outfit', sans-serif;
            color: var(--text-title);
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #8a2be2 0%, #4a00e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        header p {
            color: #888;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .card {
            background-color: #121214;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
        }

        .btn-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 30px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.6);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-secondary {
            background: #25262b;
            color: #aaa;
            box-shadow: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: #2d2e33;
            color: #fff;
            box-shadow: none;
        }

        .preview-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #66fcf1;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-area {
            background-color: #1e1e24;
            border-radius: 8px;
            padding: 25px;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
            line-height: 1.7;
            color: #e0e0e0;
        }

        .preview-area h3 {
            color: #fff;
            border-left: 3px solid #764ba2;
            padding-left: 10px;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .preview-area strong {
            color: #ffeb3b;
        }

        .preview-area p {
            margin-bottom: 1.5rem;
        }

        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background-color: #00e676;
            color: #000;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0, 230, 118, 0.3);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .error-message {
            background-color: rgba(244, 67, 54, 0.1);
            border: 1px solid #f44336;
            color: #ff8a80;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <a href="mypage/mypage.php"><img src="img/udatsu-logo.png" alt="Udatsu"></a>
        <h1>Note Copy Helper</h1>
        <p>Gyakubari Marketing Radio Automation</p>
    </header>

    <div class="card">
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i><br><br>
                <?= $error ?>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 20px; text-align: center;">
                <span style="font-size: 0.9rem; color: #888; display: block; margin-bottom: 5px;">対象エピソード:</span>
                <span style="font-size: 1.2rem; color: #fff; font-weight: 700; font-family: 'Outfit', sans-serif;">
                    <?= htmlspecialchars($ep) ?>
                </span>
            </div>

            <div class="btn-container">
                <button class="btn" id="copyBtn" onclick="copyRichText()">
                    <i class="far fa-copy"></i> Note用にコピーする
                </button>
                <a href="mypage/mypage.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> マイページに戻る
                </a>
            </div>

            <div class="preview-label">
                <i class="fas fa-eye"></i> 貼り付けプレビュー（見出し・太字確認）
            </div>

            <div class="preview-area" id="previewArea">
                <?= $articleHtml ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="toast" id="toast">
    <i class="fas fa-check-circle"></i> <span id="toastMsg">コピーしました！Noteにそのまま貼り付けられます。</span>
</div>

<script>
function copyRichText() {
    const previewArea = document.getElementById('previewArea');
    if (!previewArea) return;

    const html = previewArea.innerHTML;
    // プレーンテキスト用の整形（h3タグは改行、pタグも改行に変換）
    let text = previewArea.innerText;

    const blobHTML = new Blob([html], { type: 'text/html' });
    const blobText = new Blob([text], { type: 'text/plain' });

    if (navigator.clipboard && window.ClipboardItem) {
        const data = [new ClipboardItem({
            'text/html': blobHTML,
            'text/plain': blobText
        })];

        navigator.clipboard.write(data).then(() => {
            showToast('コピー成功！Noteのエディタにそのまま貼り付けてください。', false);
        }).catch(err => {
            console.error('Clipboard write failed: ', err);
            showToast('コピーに失敗しました。プレビュー部分を手動コピーしてください。', true);
        });
    } else {
        // フォールバック（古いブラウザやセキュリティ制約など）
        showToast('お使いの環境では自動コピーがサポートされていません。手動コピーしてください。', true);
    }
}

function showToast(msg, isError) {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toastMsg');
    
    toastMsg.innerText = msg;
    if (isError) {
        toast.style.backgroundColor = '#ff1744';
        toast.style.color = '#fff';
    } else {
        toast.style.backgroundColor = '#00e676';
        toast.style.color = '#000';
    }
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
</script>

</body>
</html>
