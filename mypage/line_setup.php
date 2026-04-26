<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($uid)) { header('Location: ../index.php'); exit(); }

$mapFile = __DIR__ . '/../users/line_map.json';
$map = file_exists($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];

$isLinked = in_array($uid, $map);

// In a real environment, you'd provide the LINE Official Account ID or URL here
$lineBotId = '@811smhsc';
$lineAddUrl = 'https://lin.ee/7HjLgwN';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>LINE Upload | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { min-height: 100vh; display: flex; flex-direction: column; background: var(--bg-dark); }
    .container { max-width: 520px; margin: 0 auto; padding: 3rem 1.5rem; flex: 1; }
    .hero { text-align: center; margin-bottom: 2rem; }
    .hero h1 { font-size: 2rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.8rem; }
    .hero h1 i { color: #06C755; }
    .hero p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; }
    
    .status-box {
      border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;
    }
    .status-box.unlinked {
      background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    }
    .status-box.linked {
      background: rgba(6, 199, 85, 0.05); border: 1px solid rgba(6, 199, 85, 0.3);
    }

    .flow { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2.5rem; }
    .flow-step { display: flex; align-items: flex-start; gap: 1.2rem;
                 background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
                 border-radius: 12px; padding: 1.2rem; }
    .flow-icon { font-size: 1.6rem; min-width: 2rem; text-align: center; color: #06C755; }
    .flow-text { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; }
    .flow-text strong { color: var(--text-white); }
    
    footer { text-align: center; padding: 2rem; }
    footer a { color: var(--text-muted); font-size: 0.9rem; text-decoration: none; }
  </style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1><i class="fab fa-line"></i> LINE Upload</h1>
    <p>日常のふとした瞬間に、LINEに音声を送るだけ。<br>シームレスにUdatsuのジャーナルに記録されます。</p>
  </div>

  <?php if ($isLinked): ?>
    <div class="status-box linked">
      <i class="fas fa-check-circle" style="font-size:2rem; color:#06C755; margin-bottom:0.5rem;"></i>
      <h3 style="margin-bottom: 0.5rem;">連携完了済みです</h3>
      <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">
        公式LINEにボイスメッセージや音声ファイルを送信すると、自動でダッシュボードに反映されます。
      </p>
    </div>
  <?php else: ?>
    <div class="status-box unlinked">
      <h3 style="margin-bottom: 0.5rem;">まだ連携されていません</h3>
      <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">
        以下のフローに従ってアカウントを紐付けてください。
      </p>
      <!-- To be active once the developer sets up the LINE bot -->
      <a href="<?php echo htmlspecialchars($lineAddUrl); ?>" target="_blank" class="btn btn-primary" style="background:#06C755; border:none; display:inline-block; text-decoration:none;"><i class="fas fa-plus"></i> 公式LINEを友だち追加</a>
    </div>
  <?php endif; ?>

  <div class="flow">
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-user-plus"></i></div>
      <div class="flow-text"><strong>1. 公式アカウントを追加</strong><br>LINEでUdatsu専用ボットを友だち追加し、メッセージを送るかメニューから「連携URL」を発行します。</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-link"></i></div>
      <div class="flow-text"><strong>2. このアカウントと紐付ける</strong><br>LINE上で送られてきたURLをタップしてログインすると、あなたのLINE IDとUdatsuアカウントが繋がります。</div>
    </div>
    <div class="flow-step">
      <div class="flow-icon"><i class="fas fa-paper-plane"></i></div>
      <div class="flow-text">
        <strong>3. 音声を送る</strong><br>
        あとはLINEのトーク画面でボイスメッセージを吹き込むか、スマホのボイスメモからLINEのトークに直接シェアするだけです！
      </div>
    </div>
  </div>

</div>
<footer><a href="dashboard.php"><i class="fas fa-chevron-left"></i> ダッシュボードに戻る</a></footer>
</body>
</html>
