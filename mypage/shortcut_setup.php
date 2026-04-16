<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
    header('Location: ../index.php');
    exit();
}

$profileFile = __DIR__ . '/../users/' . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

// Generate or display token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $profile['api_token'] = bin2hex(random_bytes(20)); // 40-char hex token
    file_put_contents($profileFile, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$apiToken   = $profile['api_token'] ?? null;
$uploadUrl  = 'https://udatsu-voyager.com/api_upload.php?token=' . ($apiToken ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Shortcut設定 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .container { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem; }
    h1 { font-size: 2rem; margin-bottom: 0.5rem; color: var(--text-white); }
    .subtitle { color: var(--text-muted); margin-bottom: 3rem; }
    .step { display: flex; gap: 1.2rem; margin-bottom: 2.5rem; align-items: flex-start; }
    .step-num { background: var(--primary-neon); color: #000; font-weight: 900;
                min-width: 2.2rem; height: 2.2rem; border-radius: 50%;
                display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .step-body h3 { margin: 0 0 0.4rem; color: var(--text-white); font-size: 1.1rem; }
    .step-body p  { margin: 0; color: var(--text-muted); line-height: 1.6; font-size: 0.95rem; }
    .token-box { background: rgba(0,255,204,0.06); border: 1px solid var(--primary-neon);
                 border-radius: 8px; padding: 1.2rem 1.5rem; margin: 1rem 0; 
                 word-break: break-all; font-family: monospace; font-size: 0.95rem;
                 color: var(--primary-neon); position: relative; }
    .copy-btn { position: absolute; top: 0.8rem; right: 0.8rem;
                background: var(--primary-neon); color: #000;
                border: none; padding: 0.3rem 0.8rem; border-radius: 5px;
                cursor: pointer; font-weight: 700; font-size: 0.8rem; }
    .copy-btn:active { opacity: 0.7; }
    .shortcut-link { display: block; text-align: center;
                     background: linear-gradient(135deg, #007aff 0%, #5ac8fa 100%);
                     color: #fff; font-weight: 700; font-size: 1.1rem;
                     padding: 1rem 2rem; border-radius: 12px; text-decoration: none;
                     margin: 1rem 0; box-shadow: 0 8px 25px rgba(0,122,255,0.4);
                     transition: transform 0.2s, box-shadow 0.2s; }
    .shortcut-link:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(0,122,255,0.5); }
    .shortcut-link i { margin-right: 0.6rem; }
    .warning-box { background: rgba(255,200,0,0.08); border: 1px solid rgba(255,200,0,0.3);
                   border-radius: 8px; padding: 1rem 1.2rem; margin: 1.5rem 0;
                   font-size: 0.9rem; color: var(--text-muted); }
    .generate-form { margin-bottom: 2rem; }
    footer { text-align: center; margin-top: 4rem; }
    footer a { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; }
  </style>
</head>
<body>
<div class="container">
  <h1>🍎 iPhone Shortcut 設定</h1>
  <p class="subtitle">ボイスメモの共有ボタンからワンタップでVoyagerにアップロードできます。</p>

  <?php if (!$apiToken): ?>
  <div class="glass-card" style="padding:2rem; text-align:center; margin-bottom:2rem;">
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">まず、あなた専用のAPIトークンを発行してください。</p>
    <form method="POST" class="generate-form">
      <input type="hidden" name="action" value="generate">
      <button type="submit" class="btn btn-primary" style="font-size:1.1rem; padding:1rem 2.5rem;">
        <i class="fas fa-key"></i> トークンを発行する
      </button>
    </form>
  </div>
  <?php else: ?>

  <div class="glass-card" style="padding:2rem; margin-bottom:2rem;">
    <p style="color:var(--text-muted); margin-bottom:0.5rem;"><i class="fas fa-key" style="color:var(--primary-neon);"></i> あなた専用のアップロードURL</p>
    <div class="token-box" id="tokenBox">
      <?= htmlspecialchars($uploadUrl) ?>
      <button class="copy-btn" onclick="copyUrl()">コピー</button>
    </div>
    <p style="font-size:0.85rem; color:var(--text-muted); margin:0.5rem 0 0;">
      ⚠️ このURLは他人に知られないようにしてください。
    </p>
    <form method="POST" style="margin-top:1rem;">
      <input type="hidden" name="action" value="generate">
      <button type="submit" style="background:none; border:none; color:var(--text-muted); font-size:0.85rem; cursor:pointer; text-decoration:underline;">
        🔄 トークンを再発行する（旧トークンは無効になります）
      </button>
    </form>
  </div>

  <h2 style="color:var(--text-white); margin-bottom:1.5rem;">📲 設定手順</h2>

  <div class="step">
    <div class="step-num">1</div>
    <div class="step-body">
      <h3>ショートカットアプリを開く</h3>
      <p>iPhoneの「ショートカット」アプリを開き、右上の <strong>「＋」</strong> ボタンで新しいショートカットを作成します。</p>
    </div>
  </div>

  <div class="step">
    <div class="step-num">2</div>
    <div class="step-body">
      <h3>「アクションを追加」→「スクリプティング」</h3>
      <p>「変数を設定」を追加し、変数名を <strong>uploadURL</strong> に、値を上のURLをコピーして貼り付けます。</p>
    </div>
  </div>

  <div class="step">
    <div class="step-num">3</div>
    <div class="step-body">
      <h3>「URLのコンテンツを取得」アクションを追加</h3>
      <p>
        <strong>URL</strong>：上のアップロードURLをそのまま貼り付け<br>
        <strong>方法</strong>：POST<br>
        <strong>ヘッダー</strong>：なし<br>
        <strong>本文作成方法</strong>：フォーム<br>
        → 「テキスト」を <strong>ファイル</strong> に変え、キーを <code>audioFile</code>、値を <strong>ショートカットの入力</strong> に設定
      </p>
    </div>
  </div>

  <div class="step">
    <div class="step-num">4</div>
    <div class="step-body">
      <h3>共有シートに追加する</h3>
      <p>ショートカット名（例：<strong>Udatsuに送る</strong>）を設定し、設定アイコン → <strong>「共有シートに表示」をON</strong> にします。</p>
    </div>
  </div>

  <div class="step">
    <div class="step-num">5</div>
    <div class="step-body">
      <h3>ボイスメモから共有 → 完了！</h3>
      <p>ボイスメモで録音を長押し → 共有 → <strong>「Udatsuに送る」</strong> をタップ → そのままダッシュボードに解析結果が追加されます🎙</p>
    </div>
  </div>

  <div class="warning-box">
    <i class="fas fa-info-circle" style="color:var(--primary-neon); margin-right:0.4rem;"></i>
    ショートカット設定後、最初の1回だけ「ネットワークアクセスを許可しますか？」と聞かれます。<strong>「許可」</strong>をタップしてください。
  </div>

  <?php endif; ?>

  <footer>
    <a href="dashboard.php">← ダッシュボードに戻る</a>
  </footer>
</div>

<script>
function copyUrl() {
  const text = document.getElementById('tokenBox').innerText.trim();
  navigator.clipboard.writeText(text).then(() => {
    event.target.textContent = 'コピー済み ✓';
    setTimeout(() => event.target.textContent = 'コピー', 2000);
  });
}
</script>
</body>
</html>
