<?php require_once __DIR__ . '/core/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>プライバシーポリシー | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { padding: 40px 20px; line-height: 1.8; color: var(--text-white); background: var(--bg-dark); max-width: 800px; margin: 0 auto; }
    h1 { margin-bottom: 30px; font-size: 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
    h2 { margin-top: 30px; margin-bottom: 15px; font-size: 1.3rem; color: #a855f7; }
    p, li { color: var(--text-muted); font-size: 0.95rem; }
    ul { margin-bottom: 20px; padding-left: 20px; }
  </style>
</head>
<body>
  <h1>プライバシーポリシー</h1>
  <p>制定日：2026年4月19日</p>

  <h2>1. 取得する情報</h2>
  <p>当サービス（Udatsu Voyager）では、以下の情報を取得・保存します。</p>
  <ul>
    <li>Googleアカウント情報（メールアドレス、氏名）</li>
    <li>本サービスにアップロードされた音声データおよびその解析テキスト（ジャーナル）</li>
    <li>LINE連携時のLINEユーザー識別子</li>
  </ul>

  <h2>2. 情報の利用目的</h2>
  <p>取得した情報は以下の目的でのみ利用します。</p>
  <ul>
    <li>AI（Gemini API等）を用いた音声データの文字起こしおよび意図抽出、インサイト生成</li>
    <li>ユーザー個人の「思考プロファイル」の構築と分析</li>
    <li>本サービスの提供、維持、保護および改善</li>
  </ul>

  <h2>3. 第三者提供について</h2>
  <p>当サービスは、法令に基づく場合を除き、取得した個人データをユーザーの同意なく第三者に提供することはありません。ただし、音声解析等のため、外部API（Google Gemini等）へデータを送信して処理を行います（AIの学習データとしては利用されない方式を採用します）。</p>

  <h2>4. 情報の管理</h2>
  <p>ユーザーの音声データや解析データは厳重に管理し、他のユーザーに公開されることは一切ありません。</p>

  <h2>5. 連携の解除について</h2>
  <p>LINE連携を含む外部サービス連携は、設定画面からいつでも解除が可能です。</p>

  <div style="margin-top: 50px; text-align: center;">
    <a href="index.php" style="color: #a855f7; text-decoration: none;">← トップページに戻る</a>
  </div>
</body>
</html>
