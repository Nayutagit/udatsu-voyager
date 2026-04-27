<?php
session_start();
$name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'ゲストさん';
$redirectLink = isset($_SESSION['uid']) ? '/mypage/mypage.php' : '/index.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>決済に失敗しました | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 60px 20px;
      text-align: center;
    }
    .container {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      max-width: 500px;
      margin: auto;
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    h1 {
      font-size: 1.8em;
      margin-bottom: 20px;
      color: #ff4444;
      font-weight: 700;
      text-shadow: 0 0 10px rgba(255, 68, 68, 0.4);
    }
    p {
      font-size: 1em;
      margin-bottom: 30px;
      line-height: 1.6;
      color: var(--text-muted);
    }
    .btn {
      display: inline-block;
      padding: 12px 28px;
      font-size: 1em;
      border: none;
      border-radius: 50px;
      background: linear-gradient(45deg, var(--primary-neon), #ffda44);
      color: #000;
      font-weight: bold;
      text-decoration: none;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
      transition: all 0.3s ease;
    }
    .btn:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
    }
    .footer {
      margin-top: 40px;
      font-size: 0.9em;
      color: var(--text-secondary);
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>決済が完了しませんでした</h1>
    <p>
      お支払い処理に失敗しました。<br>
      通信状況やカード情報をご確認のうえ、<br>
      もう一度お試しください。
    </p>
    <a class="btn" href="<?= $redirectLink ?>">マイページに戻る</a>
    <div class="footer">
      <?= htmlspecialchars($name) ?> さんの Udatsu Voyager
    </div>
  </div>
</body>
</html>