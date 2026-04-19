<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($uid)) { header('Location: ../index.php'); exit(); }

$profileFile = __DIR__ . '/../users/' . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

// Generate token if not exists
if (empty($profile['api_token'])) {
    $profile['api_token'] = bin2hex(random_bytes(20));
    file_put_contents($profileFile, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$apiToken = $profile['api_token'];
$uploadUrl = 'https://udatsu-voyager.com/api_upload.php?token=' . $apiToken;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Mail Upload | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { min-height: 100vh; display: flex; flex-direction: column; background: var(--bg-dark); }
    .container { max-width: 520px; margin: 0 auto; padding: 3rem 1.5rem; flex: 1; }
    .hero { text-align: center; margin-bottom: 3rem; }
    .hero h1 { font-size: 2rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.8rem; }
    .hero h1 i { color: #007aff; }
    .hero p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; }
    
    .token-box {
      background: rgba(0, 122, 255, 0.05);
      border: 1px solid rgba(0, 122, 255, 0.3);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 2.5rem;
      text-align: center;
    }
    .token-box p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.8rem; }
    .token-code {
      font-family: monospace;
      font-size: 1.1rem;
      color: var(--text-white);
      background: rgba(0,0,0,0.5);
      padding: 0.8rem;
      border-radius: 8px;
      word-break: break-all;
      margin-bottom: 1rem;
    }

    .flow { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2.5rem; }
    .flow-step { display: flex; align-items: flex-start; gap: 1.2rem;
                 background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
                 border-radius: 12px; padding: 1.2rem; }
    .flow-icon { font-size: 1.6rem; min-width: 2rem; text-align: center; color: #007aff; }
    .flow-text { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; }
    .flow-text strong { color: var(--text-white); }
    
    footer { text-align: center; padding: 2rem; }
    footer a { color: var(--text-muted); font-size: 0.9rem; text-decoration: none; }
  </style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1><i class="fas fa-envelope"></i> Mail Upload</h1>
    <p>ボイスメモから<strong style="color:var(--text-white);">メール添付</strong>で送信するだけで<br>バックグラウンドで自動的に解析されます</p>
  </div>

  <div class="flow">
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-microphone-alt"></i></div>
      <div class="flow-text"><strong>1. ボイスメモで録音する</strong><br>iPhone等のボイスメモアプリを開き、思考を録音します。（※メール添付の制限上、1ファイルあたり50分程度まで可能です）</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-share-square"></i></div>
      <div class="flow-text"><strong>2. 共有ボタンからメールを選択</strong><br>録音データを長押しし「共有」からメールアプリを起動します。</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-paper-plane"></i></div>
      <div class="flow-text">
        <strong>3. 指定アドレス宛に送信する</strong><br>
        管理者が設定したn8nのWebhookメールアドレス宛に送信してください。
      </div>
    </div>
  </div>

  <div class="token-box">
    <p>【開発・管理者用】あなたの連携APIトークン</p>
    <div class="token-code"><?= htmlspecialchars($apiToken) ?></div>
    <p style="font-size:0.8rem; margin:0;"><i class="fas fa-info-circle"></i> このトークンを用いた `api_upload.php` への連携をn8nで構築してください。</p>
  </div>

</div>
<footer><a href="dashboard.php"><i class="fas fa-chevron-left"></i> ダッシュボードに戻る</a></footer>
</body>
</html>
