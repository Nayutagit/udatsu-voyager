<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>プライバシーポリシー | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      background-image: url('img/concrete_bg.png');
      background-size: cover;
      background-position: center;
      padding: 40px 20px;
      line-height: 1.6;
    }
    .content-box {
      max-width: 800px;
      margin: 0 auto;
      padding: 40px;
      background: rgba(10, 10, 10, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      backdrop-filter: blur(10px);
    }
    h1 { font-size: 2rem; color: var(--primary-neon); margin-bottom: 30px; text-align: center; }
    h2 { font-size: 1.3rem; margin-top: 30px; margin-bottom: 15px; color: var(--text-white); border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 5px; }
    p { color: var(--text-muted); margin-bottom: 15px; }
    ul { color: var(--text-muted); margin-bottom: 15px; padding-left: 20px; }
    li { margin-bottom: 5px; }
    .important-clause {
      background: rgba(252, 200, 0, 0.1);
      border-left: 4px solid var(--primary-neon);
      padding: 15px;
      margin: 20px 0;
      color: var(--text-white);
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="content-box">
    <h1>プライバシーポリシー</h1>
    
    <p>Udatsu Voyager（以下「本サービス」）は、ユーザーのプライバシーと個人情報の保護を最重要視しています。</p>

    <h2>1. 取得する情報</h2>
    <p>本サービスは、以下の情報を取得・保存します。</p>
    <ul>
      <li>Googleアカウント情報（メールアドレス、氏名）</li>
      <li>ユーザーがアップロードまたはLINE経由で送信した音声データ</li>
      <li>音声データから生成されたテキストデータ（文字起こし、記事など）</li>
    </ul>

    <h2>2. データの利用目的と閲覧制限（重要）</h2>
    <p>本サービスにアップロードされた音声データおよびテキストデータは、AIによる解析処理およびユーザー自身の閲覧機能の提供にのみ使用されます。</p>
    <div class="important-clause">
      サーバーの保守やエラー対応の目的以外で、運営がユーザーの音声データや文章を勝手に閲覧・視聴することはありません。
    </div>

    <h2>3. データの保管とセキュリティ</h2>
    <p>データは堅牢なクラウドインフラ（Google Firebase等）を用いて安全に保管されます。ユーザー自身のデータは、認証された本人のみがアクセスできる仕組みとなっており、第三者に公開されることはありません。</p>

    <h2>4. 第三者提供</h2>
    <p>本サービスは、法令に基づく場合を除き、ユーザーの同意なしに個人情報を第三者に提供することはありません。（ただし、AI解析のためにGoogle Gemini API等へ一時的にデータが送信されますが、これらは学習に利用されない設定となっています。）</p>

    <div style="text-align: center; margin-top: 50px;">
      <a href="index.php" style="color: var(--primary-neon); text-decoration: none;">&laquo; トップページへ戻る</a>
    </div>
  </div>
</body>
</html>
