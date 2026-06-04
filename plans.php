<?php
session_start();
$user_name = $_SESSION['user_name'] ?? 'ゲストさん';
$user_plan = $_SESSION['user_plan'] ?? 'guest';

$plan_label = match ($user_plan) {
  'guest'     => 'ゲスト',
  'trial'     => 'トライアルプラン',
  'light'     => 'ライトプラン',
  'standard'  => 'スタンダードプラン',
  'premium'   => 'プレミアムプラン',
  'admin'     => '管理者',
  default     => '不明',
};
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu プラン紹介</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      padding: 2em;
      text-align: center;
    }
    .logo {
      width: 120px;
      margin-bottom: 1em;
      cursor: pointer;
      filter: drop-shadow(0 0 8px rgba(252, 200, 0, 0.6));
      transition: all 0.3s ease;
    }
    .logo:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 0 15px rgba(252, 200, 0, 0.6));
    }
    h1 {
      font-size: 2em;
      margin-bottom: 0.5em;
      color: var(--text-white);
      text-shadow: 0 0 10px rgba(252, 200, 0, 0.5);
    }
    h2 {
      font-size: 1.4em;
      margin-top: 2em;
      margin-bottom: 1em;
      color: var(--primary-neon);
    }
    table {
      width: 100%;
      max-width: 900px;
      margin: 1em auto;
      border-collapse: collapse;
      background: var(--bg-panel);
      backdrop-filter: blur(10px);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    th, td {
      padding: 1em;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      font-size: 0.95em;
      color: var(--text-white);
    }
    th {
      background-color: rgba(252, 200, 0, 0.2);
      color: var(--primary-neon);
      font-weight: 700;
    }
    tr:hover td {
      background-color: rgba(255, 255, 255, 0.05);
    }
    .plan-box {
      margin: 2em auto;
      max-width: 700px;
      text-align: left;
      background: var(--bg-panel);
      padding: 2rem;
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
    }
    .plan-box h3 {
      font-size: 1.2em;
      margin-top: 1em;
      color: var(--primary-neon);
    }
    .plan-box h3:first-child {
      margin-top: 0;
    }
    .plan-box p {
      margin: 0.5em 0 1em;
      color: var(--text-muted);
      line-height: 1.6;
    }
    .button {
      display: inline-block;
      background: linear-gradient(45deg, var(--primary-neon), #ffda44);
      color: #000;
      padding: 0.7em 1.5em;
      border-radius: 30px;
      text-decoration: none;
      font-weight: bold;
      margin-top: 1em;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
      transition: all 0.3s ease;
    }
    .button:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
    }
    .welcome {
      font-size: 1.1em;
      margin-top: 1em;
      font-weight: bold;
      color: var(--text-white);
    }
    .welcome strong {
      color: var(--primary-neon);
    }
    .note {
      font-size: 0.9em;
      color: var(--text-muted);
      margin-top: 3em;
    }
    .back-link {
      margin-top: 2em;
      display: inline-block;
      text-decoration: none;
      color: var(--text-white);
      transition: all 0.3s ease;
    }
    .back-link:hover {
      color: var(--primary-neon);
    }
  </style>
</head>
<body>
  <a href="index.php">
    <img src="img/udatsu-logo.png" alt="Udatsu" class="logo">
  </a>
  <h1>Udatsu プラン紹介</h1>
  <p class="welcome"><?= htmlspecialchars($user_name) ?> の現在のステータス：<strong><?= htmlspecialchars($plan_label) ?></strong></p>

  <h2>プラン比較表</h2>
  <table>
    <tr>
      <th>機能</th>
      <th>ゲスト</th>
      <th>トライアル</th>
      <th>ライト</th>
      <th>スタンダード</th>
      <th>プレミアム</th>
    </tr>
    <tr>
      <td>整文回数（1日）</td>
      <td>1回（全文×）</td>
      <td>1回</td>
      <td>3回</td>
      <td>5回</td>
      <td>10回</td>
    </tr>
    <tr>
      <td>音声長（1回）</td>
      <td>3分</td>
      <td>3分</td>
      <td>10分</td>
      <td>15分</td>
      <td>60分</td>
    </tr>
    <tr>
      <td>整文タイプ</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
    </tr>
    <tr>
      <td>コピー可否</td>
      <td>一部のみ</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
    </tr>
    <tr>
      <td>マイページ投稿</td>
      <td>×</td>
      <td>○（1件まで）</td>
      <td>5件まで</td>
      <td>10件まで</td>
      <td>無制限</td>
    </tr>
    <tr>
      <td>投稿編集</td>
      <td>×</td>
      <td>×</td>
      <td>○</td>
      <td>○</td>
      <td>○</td>
    </tr>
    <tr>
      <td>掲載リクエスト</td>
      <td>×</td>
      <td>×</td>
      <td>○</td>
      <td>○（優先）</td>
      <td>○（承認）</td>
    </tr>
    <tr>
      <td>再整文化</td>
      <td>×</td>
      <td>×</td>
      <td>○（同日1回）</td>
      <td>○（同日2回）</td>
      <td>○（無制限）</td>
    </tr>
    <tr>
      <td>削除後の保存</td>
      <td>×</td>
      <td>×</td>
      <td>1ヶ月</td>
      <td>1ヶ月</td>
      <td>1ヶ月</td>
    </tr>
  </table>

  <div class="plan-box">
    <h3>Udatsu とは？</h3>
    <p>声から始める思考整理と発信。Udatsu Voyagerは、音声をアップロードするだけで自動的に文字起こし＆整文してくれるツールです。</p>

    <h3>プラン選びのヒント</h3>
    <p>まずはトライアルで1件保存してみて、「もっと使いたい」と思ったらライトへ。<br>日々の発信や仕事に使うならスタンダードかプレミアムがおすすめ。</p>

    <a href="mypage/membership.php" class="button">プレミアムにアップグレード</a>
  </div>

  <p class="note">⚠️ Safari / Chrome などの外部ブラウザを推奨します。アプリ内ブラウザでは動作が不安定な場合があります。</p>

  <a href="index.php" class="back-link">← トップページに戻る</a>
</body>
</html>