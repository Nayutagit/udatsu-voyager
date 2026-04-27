<?php
session_start();
require_once __DIR__ . '/../../firebase/firestore.php';

$name = $_SESSION['user_name'] ?? 'ゲストさん';
$email = $_SESSION['uid'] ?? '';
$current_plan = $_SESSION['user_plan'] ?? 'trial';
$new_plan = $_SESSION['pending_plan'] ?? '（不明）';

// 表示用ラベル
$plan_labels = [
  'light' => 'ライトプラン',
  'standard' => 'スタンダードプラン',
  'premium' => 'プレミアムプラン'
];
$plan_display = $plan_labels[$new_plan] ?? '新しいプラン';

// 🔥 無条件で保存処理を実行（ただし new_plan が有効なもののみ）
$eligible_plans = ['light', 'standard', 'premium'];
if ($email && in_array($new_plan, $eligible_plans, true)) {
  error_log("✅ Firestore保存条件クリア → 書き込み開始: $email / $new_plan");

  $saved = saveUserPlanToFirestore($email, $new_plan);

  if ($saved) {
    error_log("✅ Firestore保存成功 → plan = $new_plan");
    $_SESSION['user_plan'] = $new_plan;
  } else {
    error_log("❌ Firestore保存失敗: $email / $new_plan");
  }
} else {
  error_log("⚠️ Firestore書き込みスキップ: email=$email, current_plan=$current_plan, new_plan=$new_plan");
}

// 🎯 セッションループ防止
unset($_SESSION['pending_plan']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>プラン変更完了 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
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
      max-width: 520px;
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
      font-size: 1.9em;
      margin-bottom: 20px;
      color: var(--text-white);
      font-weight: 700;
    }
    .message {
      font-size: 1.1em;
      line-height: 1.7;
      margin-bottom: 30px;
      color: var(--text-muted);
    }
    strong {
      color: var(--primary-neon);
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
    <h1><?= htmlspecialchars($plan_display) ?>への決済が完了しました！</h1>
    <div class="message">
      <?= htmlspecialchars($name) ?> さん、ご利用ありがとうございます。<br><br>
      新しいプランを完全に反映するために、<br>
      <strong>一度ログアウトして再ログインしてください。</strong><br><br>
      反映までに時間がかかる場合があります。<br>
      24時間経っても反映されない場合は、お問い合わせください。
    </div>
    <a class="btn" href="../logout.php">再ログインする</a>
    <div class="footer">
      Udatsu Voyager
    </div>
  </div>
</body>
</html>