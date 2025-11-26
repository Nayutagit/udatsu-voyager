<?php
// 🔁 共通ブートストラップを読み込み
require_once __DIR__ . '/core/bootstrap.php';

// 🔁 表示用パラメータ取得（GETが優先、なければセッション由来で補完）
$planParam  = $_GET['plan']  ?? $userPlan;
$limitParam = $_GET['limit'] ?? $maxSizeMB;
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ファイルサイズエラー | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      text-align: center;
    }
    .error-box {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      border-radius: 20px;
      padding: 40px 30px;
      max-width: 500px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    h1 {
      font-size: 1.5em;
      color: #ff4444;
      margin-bottom: 1em;
      font-weight: 700;
      text-shadow: 0 0 10px rgba(255, 68, 68, 0.4);
    }
    p {
      font-size: 1em;
      margin-bottom: 1.2em;
      color: var(--text-muted);
      line-height: 1.6;
    }
    strong {
      color: var(--primary-neon);
    }
    a.button {
      display: inline-block;
      padding: 12px 28px;
      margin: 8px;
      background: linear-gradient(45deg, var(--primary-neon), #ffda44);
      color: #000;
      text-decoration: none;
      border-radius: 50px;
      font-weight: bold;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
      transition: all 0.3s ease;
    }
    a.button:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
    }
    .small-note {
      font-size: 0.85em;
      color: var(--text-secondary);
      margin-top: 2em;
    }
  </style>
</head>
<body>

  <div class="error-box">
    <h1>おっと、ファイルがちょっと大きすぎたようです！</h1>
    <p>
      現在ご利用中のプラン <strong><?= htmlspecialchars($planParam) ?></strong> では、<br>
      <strong>最大 <?= htmlspecialchars($limitParam) ?>MB</strong> までの音声ファイルに対応しています。
    </p>
    <p>
      ファイルをもう少し短くするか、<br>
      より長い音声を扱いたい方はメンバーシップをご検討ください😊
    </p>

    <a href="plans.php" class="button">メンバーシップを確認する</a>
    <a href="index.php" class="button">← ファイルを選び直す</a>

    <p class="small-note">声上げてこ、うだつ上げてこ！</p>
  </div>

</body>
</html>