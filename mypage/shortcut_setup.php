<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($uid)) { header('Location: ../index.php'); exit(); }

$profileFile = __DIR__ . '/../users/' . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

// Generate or display token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile['api_token'] = bin2hex(random_bytes(20));
    file_put_contents($profileFile, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$apiToken = $profile['api_token'] ?? null;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>iPhone連携 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { min-height: 100vh; display: flex; flex-direction: column; }
    .container { max-width: 520px; margin: 0 auto; padding: 3rem 1.5rem; flex: 1; }
    .hero { text-align: center; margin-bottom: 3rem; }
    .hero h1 { font-size: 2.2rem; margin-bottom: 0.5rem; }
    .hero p { color: var(--text-muted); font-size: 1rem; line-height: 1.6; }
    .flow { display: flex; flex-direction: column; gap: 1rem; margin: 2rem 0 2.5rem; }
    .flow-step { display: flex; align-items: center; gap: 1rem;
                 background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
                 border-radius: 12px; padding: 1rem 1.2rem; }
    .flow-icon { font-size: 1.6rem; min-width: 2rem; text-align: center; }
    .flow-text { color: var(--text-muted); font-size: 0.95rem; line-height: 1.4; }
    .flow-text strong { color: var(--text-white); }
    .download-btn {
      display: block; width: 100%; padding: 1.2rem;
      background: linear-gradient(135deg, #007aff, #5ac8fa);
      color: #fff; font-weight: 800; font-size: 1.2rem;
      border: none; border-radius: 16px; cursor: pointer;
      text-decoration: none; text-align: center;
      box-shadow: 0 8px 30px rgba(0,122,255,0.4);
      transition: transform 0.2s, box-shadow 0.2s;
      margin-bottom: 1rem;
    }
    .download-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,122,255,0.55); }
    .download-btn i { margin-right: 0.5rem; }
    .regen-link { display: block; text-align: center; color: var(--text-muted); 
                  font-size: 0.85rem; text-decoration: underline; cursor: pointer;
                  background: none; border: none; width: 100%; margin-top: 0.5rem; }
    .generate-section { text-align: center; padding: 3rem 1rem; }
    footer { text-align: center; padding: 2rem; }
    footer a { color: var(--text-muted); font-size: 0.9rem; text-decoration: none; }
  </style>
</head>
<body>
<div class="container">
  <div class="hero">
    <div style="font-size:3.5rem; margin-bottom:1rem;">🍎</div>
    <h1>iPhone連携</h1>
    <p>ボイスメモの<strong style="color:var(--text-white);">共有ボタン</strong>を押すだけで<br>Voyagerに自動アップロードできます</p>
  </div>

  <?php if ($apiToken): ?>

  <!-- PREREQUISITE WARNING -->
  <div style="background: rgba(255,200,0,0.1); border: 1px solid rgba(255,200,0,0.4); border-radius: 12px; padding: 1.2rem 1.4rem; margin-bottom: 2rem;">
    <p style="margin:0 0 0.6rem; color: var(--primary-neon); font-weight:700;">⚙️ まず1回だけiOS設定が必要です</p>
    <p style="margin:0; color:var(--text-muted); font-size:0.9rem; line-height:1.6;">
      <strong style="color:var(--text-white);">設定アプリ</strong> → <strong style="color:var(--text-white);">ショートカット</strong> →
      <strong style="color:var(--primary-neon);">「信頼されていないショートカットを許可」をON</strong><br>
      <span style="font-size:0.82rem; opacity:0.7;">(この設定がないとiOSがファイルを開けません)</span>
    </p>
  </div>

  <div class="flow">
    <div class="flow-step">
      <div class="flow-icon">⬇️</div>
      <div class="flow-text"><strong>ショートカットをダウンロード</strong><br>ボタンを押してインストール（1回だけ）</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon">🎙</div>
      <div class="flow-text"><strong>ボイスメモを開く</strong><br>録音を長押し → 共有ボタン</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon">✅</div>
      <div class="flow-text"><strong>「Udatsuに送る」をタップ</strong><br>あとは自動で解析 → ダッシュボードに表示</div>
    </div>
  </div>

  <a href="generate_shortcut.php" class="download-btn">
    <i class="fab fa-apple"></i> ショートカットをダウンロード
  </a>
  <form method="POST">
    <button type="submit" class="regen-link">🔄 ショートカットを再発行する</button>
  </form>

  <?php else: ?>
  <div class="generate-section">
    <p style="color:var(--text-muted); margin-bottom:2rem;">はじめに専用ショートカットを発行してください</p>
    <form method="POST">
      <button type="submit" class="download-btn">
        <i class="fas fa-key"></i> ショートカットを発行する
      </button>
    </form>
  </div>
  <?php endif; ?>

</div>
<footer><a href="dashboard.php">← ダッシュボードに戻る</a></footer>
</body>
</html>
