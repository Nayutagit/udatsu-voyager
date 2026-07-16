<?php
require_once __DIR__ . '/core/bootstrap.php';

// キャッシュ無効化
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!empty($uid)) {
  header("Location: mypage/mypage.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu | 声から始める思考資産プラットフォーム</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="manifest" href="manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Udatsu">
  <link rel="apple-touch-icon" href="img/udatsu-logo.png">
  <style>
    /* ==========================================================================
       Udatsu LP Premium Gym Style Override
       ========================================================================== */
    body {
      background-color: #060606;
      color: var(--text-primary);
      font-family: "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Noto Sans JP", sans-serif;
      overflow-x: hidden;
    }

    /* Common Layout */
    .lp-container {
      width: 100%;
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 24px;
    }

    /* Header */
    .lp-header {
      width: 100%;
      height: 72px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      position: sticky;
      top: 0;
      background: rgba(6, 6, 6, 0.85);
      backdrop-filter: blur(20px);
      z-index: 100;
    }
    .header-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.3rem;
      font-weight: 700;
      color: #fff;
      text-decoration: none;
    }
    .header-logo img {
      width: 36px;
      height: 36px;
      filter: drop-shadow(0 0 8px rgba(252, 200, 0, 0.4));
    }
    .header-nav {
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .btn-login-header {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #fff;
      padding: 8px 16px;
      border-radius: 99px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-login-header:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.3);
    }

    /* Hero Section */
    .hero-sec {
      padding: 80px 0 100px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .hero-badge {
      display: inline-block;
      padding: 6px 16px;
      background: rgba(252, 200, 0, 0.1);
      border: 1px solid rgba(252, 200, 0, 0.3);
      color: var(--brand);
      font-size: 0.85rem;
      font-weight: 700;
      border-radius: 99px;
      margin-bottom: 24px;
      letter-spacing: 0.05em;
    }
    .hero-sec h1 {
      font-size: clamp(2.2rem, 5vw, 4rem);
      line-height: 1.25;
      font-weight: 900;
      color: #fff;
      margin-bottom: 24px;
      letter-spacing: -0.01em;
    }
    .hero-sec h1 span {
      background: linear-gradient(135deg, #fff, #bbb);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .hero-sec h1 .highlight-text {
      background: linear-gradient(135deg, var(--brand), #ffe066);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      display: inline-block;
    }
    .hero-desc {
      font-size: clamp(1rem, 1.8vw, 1.25rem);
      color: var(--text-secondary);
      max-width: 680px;
      margin: 0 auto 40px;
      line-height: 1.7;
    }
    .cta-group {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
    }
    .btn-cta-primary {
      background: linear-gradient(135deg, var(--brand), #ffd633);
      color: #000;
      font-size: 1.1rem;
      font-weight: 700;
      padding: 16px 40px;
      border-radius: 99px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 8px 30px rgba(252, 200, 0, 0.25);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      text-decoration: none;
    }
    .btn-cta-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 35px rgba(252, 200, 0, 0.4);
    }
    .btn-cta-secondary {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
      padding: 14px 36px;
      border-radius: 99px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn-cta-secondary:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.2);
    }

    /* Section Styles */
    .section-lp {
      padding: 90px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.03);
    }
    .bg-darker {
      background-color: #030303;
    }
    .section-title-lp {
      text-align: center;
      margin-bottom: 60px;
    }
    .section-title-lp span {
      font-size: 0.85rem;
      color: var(--brand);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.15em;
      display: block;
      margin-bottom: 12px;
    }
    .section-title-lp h2 {
      font-size: 2.2rem;
      font-weight: 800;
      color: #fff;
    }

    /* Grid layout for features */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 30px;
    }
    .feature-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      padding: 40px 30px;
      border-radius: 16px;
      transition: all 0.3s;
    }
    .feature-card:hover {
      border-color: rgba(252, 200, 0, 0.15);
      background: rgba(255, 255, 255, 0.03);
      transform: translateY(-4px);
    }
    .feature-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: rgba(252, 200, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--brand);
      font-size: 1.4rem;
      margin-bottom: 24px;
    }
    .feature-card h3 {
      font-size: 1.25rem;
      color: #fff;
      margin-bottom: 14px;
      font-weight: 700;
    }
    .feature-card p {
      color: var(--text-secondary);
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /* Two Methods Section */
    .method-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
    }
    .method-box {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 20px;
      padding: 40px;
      position: relative;
      overflow: hidden;
    }
    .method-box::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: var(--brand);
    }
    .method-box.accent-teal::before {
      background: #38bdf8;
    }
    .method-tag {
      display: inline-block;
      padding: 4px 12px;
      background: rgba(255, 255, 255, 0.05);
      font-size: 0.8rem;
      font-weight: 700;
      border-radius: 4px;
      color: #fff;
      margin-bottom: 20px;
    }
    .method-box h3 {
      font-size: 1.6rem;
      color: #fff;
      margin-bottom: 16px;
      font-weight: 700;
    }
    .method-box p {
      color: var(--text-secondary);
      font-size: 1rem;
      line-height: 1.7;
      margin-bottom: 24px;
    }
    .method-list {
      list-style: none;
    }
    .method-list li {
      display: flex;
      align-items: center;
      gap: 12px;
      color: var(--text-primary);
      margin-bottom: 12px;
      font-size: 0.95rem;
    }
    .method-list li i {
      color: var(--brand);
    }
    .method-box.accent-teal .method-list li i {
      color: #38bdf8;
    }

    /* Demo Section */
    .vj-demo-list {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }
    .vj-demo-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.3s;
    }
    .vj-demo-card:hover {
      border-color: rgba(252, 200, 0, 0.2);
    }
    .vj-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
    }
    .vj-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #2a2a2a;
    }
    .vj-meta {
      font-size: 0.8rem;
      color: var(--text-muted);
    }
    .vj-card-title {
      font-size: 1.1rem;
      color: #fff;
      font-weight: 700;
      margin-bottom: 12px;
    }
    .vj-card-desc {
      color: var(--text-secondary);
      font-size: 0.9rem;
      line-height: 1.5;
      margin-bottom: 20px;
      flex-grow: 1;
    }
    .vj-card-tag {
      font-size: 0.75rem;
      color: var(--brand);
      background: rgba(252, 200, 0, 0.1);
      padding: 4px 8px;
      border-radius: 4px;
      align-self: flex-start;
    }

    /* Pricing Section */
    .price-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 30px;
    }
    .price-card {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 20px;
      padding: 40px 30px;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
    }
    .price-card.popular {
      border-color: var(--brand);
      background: rgba(252, 200, 0, 0.02);
      box-shadow: 0 10px 40px rgba(252, 200, 0, 0.08);
    }
    .badge-popular {
      position: absolute;
      top: -14px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--brand);
      color: #000;
      font-size: 0.75rem;
      font-weight: 800;
      padding: 4px 16px;
      border-radius: 99px;
      letter-spacing: 0.05em;
    }
    .price-card h3 {
      font-size: 1.4rem;
      color: #fff;
      margin-bottom: 12px;
      font-weight: 700;
    }
    .price-amount {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--brand);
      margin-bottom: 24px;
    }
    .price-amount span {
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 400;
    }
    .price-features {
      list-style: none;
      margin: 0 0 32px;
      text-align: left;
    }
    .price-features li {
      font-size: 0.9rem;
      color: var(--text-secondary);
      padding: 10px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .price-features li i {
      color: var(--brand);
    }

    /* Footer */
    .lp-footer {
      padding: 60px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      text-align: center;
    }
    .lp-footer-links {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-bottom: 24px;
    }
    .lp-footer-links a {
      color: var(--text-muted);
      font-size: 0.85rem;
      text-decoration: none;
      transition: color 0.2s;
    }
    .lp-footer-links a:hover {
      color: #fff;
    }
    .lp-copy {
      color: var(--text-muted);
      font-size: 0.8rem;
    }

    /* Loading Spinner */
    .spinner-wrap {
      grid-column: 1 / -1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
      color: var(--text-muted);
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .features-grid, .price-grid, .vj-demo-list {
        grid-template-columns: 1fr 1fr;
      }
      .price-grid {
        max-width: 680px;
        margin: 0 auto;
      }
    }
    @media (max-width: 768px) {
      .features-grid, .price-grid, .vj-demo-list, .method-row {
        grid-template-columns: 1fr;
      }
      .lp-header {
        padding: 0 16px;
      }
      .section-lp {
        padding: 60px 0;
      }
    }
  </style>
</head>
<body>

  <!-- ============================================================
       HEADER
       ============================================================ -->
  <header class="lp-header">
    <div class="lp-container" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
      <a href="index.php" class="header-logo">
        <img src="img/udatsu-logo.png" alt="Udatsu Logo">
        <span>Udatsu</span>
      </a>
      <div class="header-nav">
        <button id="login-btn-header" class="btn-login-header">Googleログイン</button>
      </div>
    </div>
  </header>

  <!-- ============================================================
       HERO
       ============================================================ -->
  <section class="hero-sec">
    <div class="lp-container">
      <span class="hero-badge">声から始める、思考資産プラットフォーム</span>
      <h1>
        声と思考を蓄積し、<br>
        <span class="highlight-text">人生のうだつを上げる。</span>
      </h1>
      <p class="hero-desc">
        「書く時間がない」「発信が続かない」と悩む人のための、声のブログ・思考資産プラットフォーム。<br>水増しされたAI記事を作るのではなく、あなたの肉声の温度感と言葉づかいをそのまま資産にする。ボイスジャーナリングで、あなたの「思考と経験」を文字と音声で蓄積していきます。
      </p>
      <div class="cta-group">
        <button id="login-btn" class="btn-cta-primary">
          <i class="fab fa-google"></i> Googleログインして無料で試す
        </button>
      </div>
    </div>
  </section>

  <!-- ============================================================
       CONCEPT
       ============================================================ -->
  <section class="section-lp bg-darker">
    <div class="lp-container">
      <div class="section-title-lp">
        <span>Concept</span>
        <h2>喋るだけで、あなたの言葉がそのまま資産になる。</h2>
      </div>
      <div class="features-grid">
        <!-- Feature 1 -->
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-pen-nib"></i></div>
          <h3>キーボードで悩む時間を、喋る時間に変える。</h3>
          <p>何を書くか何時間も悩むのはやめましょう。歩きながら、思いついた瞬間にスマホに喋るだけ。あなたらしい言葉の癖や温度感を残したまま、読み応えのある記事へと整えます。</p>
        </div>
        <!-- Feature 2 -->
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
          <h3>簡単だから続けられる、言語化の習慣</h3>
          <p>書くための特別なスキルや時間は必要ありません。日々の気づきやアイデアを声で録音するだけで、自動で整理・整文化され、発信可能な記事として蓄積されていきます。</p>
        </div>
        <!-- Feature 3 -->
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-brain"></i></div>
          <h3>あなたの「思考の分身」をAIが学習</h3>
          <p>ただの文字起こしツールではありません。蓄積されたあなたのボイスデータから思考パターンや独自の口調を学習し、あなただけの「思考の分身AI」と対話できるようになります。</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
       METHODS
       ============================================================ -->
  <section class="section-lp">
    <div class="lp-container">
      <div class="section-title-lp">
        <span>Product Features</span>
        <h2>録音から蓄積、対話までカバーする言語化システム。</h2>
      </div>
      <div class="method-row">
        <!-- Method 1 -->
        <div class="method-box">
          <span class="method-tag" style="background: rgba(252, 200, 0, 0.1); color: var(--brand);">VOICE ARCHIVE</span>
          <h3>「喋って送る」だけで毎日更新</h3>
          <p>「できる」と「やる」は別。だからこそ、喋って送るだけという極限まで低いハードルで、毎日でも記事が更新できる継続の仕組み（うだつ）を用意しました。</p>
          <ul class="method-list">
            <li><i class="fas fa-check-circle"></i> LINEまたはiOSアプリから簡単に録音</li>
            <li><i class="fas fa-check-circle"></i> Gemini AIによる超高速な整文化・要約</li>
            <li><i class="fas fa-check-circle"></i> 蓄積された音声から生成される「思考クイズ」</li>
          </ul>
        </div>
        <!-- Method 2 -->
        <div class="method-box accent-teal">
          <span class="method-tag" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8;">AI CHAT & QUIZ</span>
          <h3>AIの分身と対話し、思考を深める</h3>
          <p>蓄積したジャーナルをもとに、AIがあなたの思考の分身を構築。さらに過去の投稿をもとにクイズを出題し、あなたの知識の定着やアウトプットを加速します。</p>
          <ul class="method-list">
            <li><i class="fas fa-check-circle"></i> 過去のジャーナルから学習した「思考の分身AIチャット」</li>
            <li><i class="fas fa-check-circle"></i> 自分の知識から出題される「思考チェッククイズ」</li>
            <li><i class="fas fa-check-circle"></i> 気づきを促し、アイデアを再発見する対話エンジン</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
       REAL DEMO (VOICE ARCHIVES)
       ============================================================ -->
  <section class="section-lp bg-darker">
    <div class="lp-container">
      <div class="section-title-lp">
        <span>Real Performance</span>
        <h2>思考資産（ボイスジャーナリング）の具体例</h2>
      </div>
      <p style="text-align: center; color: var(--text-secondary); max-width: 600px; margin: -40px auto 50px; line-height: 1.6;">
        実際に録音された音声から、AIが自動解析・作成した記事の具体例です。話した熱量や言葉づかいがそのまま丁寧な文章に整理される様子をご確認ください。
      </p>

      <div class="vj-demo-list" id="nayuta-vj-list">
        <!-- Spinner -->
        <div class="spinner-wrap" id="vj-spinner">
          <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--brand); margin-bottom: 12px;"></i>
          <p>思考資産をロード中...</p>
        </div>
      </div>
    </div>
  </section>


  <!-- ============================================================
       FOOTER
       ============================================================ -->
  <footer class="lp-footer">
    <div class="lp-container">
      <div class="lp-footer-links">
        <a href="terms.php">利用規約</a>
        <a href="privacy.php">プライバシーポリシー</a>
      </div>
      <p class="lp-copy">&copy; 2026 Udatsu. All Rights Reserved. via Firebase Secure System</p>
    </div>
  </footer>

  <!-- ============================================================
       SCRIPTS (FIREBASE AUTH & LOGICS)
       ============================================================ -->
  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.11.0/firebase-app.js";
    import {
      getAuth,
      signInWithRedirect,
      signInWithPopup,
      getRedirectResult,
      GoogleAuthProvider
    } from "https://www.gstatic.com/firebasejs/10.11.0/firebase-auth.js";
    import {
      getFirestore,
      doc,
      getDoc,
      setDoc
    } from "https://www.gstatic.com/firebasejs/10.11.0/firebase-firestore.js";

    // Firebase初期化
    const app = initializeApp({
      apiKey: "AIzaSyBU9yT3jiTo2FlkHF-nQH3mCEa-VqvZnuY",
      authDomain: "udatsu-ageteko.firebaseapp.com",
      projectId: "udatsu-ageteko",
      storageBucket: "udatsu-ageteko.appspot.com",
      messagingSenderId: "523211796022",
      appId: "1:523211796022:web:fb83d29ef10d94547a3297"
    });

    const auth = getAuth(app);
    const db = getFirestore(app);
    const provider = new GoogleAuthProvider();

    // 共通のログイン完了処理
    async function completeLogin(user) {
      const userEmail = user.email || (user.uid + "@noemail.com");
      
      const docRef = doc(db, "users", userEmail);
      const docSnap = await getDoc(docRef);
      let plan = "trial";
      if (!docSnap.exists()) {
        await setDoc(docRef, {
          uid: user.uid,
          email: userEmail,
          name: user.displayName || "ゲストさん",
          plan: "trial",
          createdAt: new Date()
        });
      } else {
        plan = docSnap.data().plan || "trial";
      }

      const formData = new URLSearchParams();
      formData.append('email', userEmail);
      formData.append('plan', plan);
      formData.append('name', user.displayName || 'ゲストさん');
      formData.append('uid', user.uid);

      try {
        const saveRes = await fetch("save_plan.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          credentials: "include",
          body: formData.toString()
        });
        const rawText = await saveRes.text();
        let saveData;
        try {
          saveData = JSON.parse(rawText);
        } catch (e) {
          throw new Error("サーバー応答がJSONではありません: " + rawText);
        }
        
        if (saveData.status === 'ok') {
          location.href = "mypage/mypage.php";
        } else {
          throw new Error(saveData.message || "不明なエラー");
        }
      } catch (err) {
        showError("ログイン処理エラー: " + err.message);
      }
    }

    // リダイレクト後の処理
    async function handleRedirect() {
      try {
        const result = await getRedirectResult(auth);
        if (result) {
          await completeLogin(result.user);
        }
      } catch (error) {
        alert("ログインエラーが発生しました: " + error.message);
      }
    }

    function showError(msg) {
      let box = document.getElementById("err-box");
      if (!box) {
        box = document.createElement("div");
        box.id = "err-box";
        box.style.cssText = "position:fixed;top:0;left:0;right:0;background:#c00;color:#fff;padding:15px 20px;font-size:0.85rem;z-index:9999;white-space:pre-wrap;word-break:break-all;";
        document.body.prepend(box);
      }
      box.textContent = "⚠️ " + msg;
    }

    // ページ読み込み時にリダイレクト結果を確認
    handleRedirect();

    window.onerror = function(msg, url, lineNo, columnNo, error) {
      showError("JS Error: " + msg + " at " + url + ":" + lineNo);
      return false;
    };

    // ログインボタンバインディング
    const setupLoginBtn = (btnId) => {
      const btn = document.getElementById(btnId);
      if (!btn) return;
      btn.addEventListener("click", async () => {
        btn.style.opacity = "0.7";
        btn.style.pointerEvents = "none";
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        
        try {
          const result = await signInWithPopup(auth, provider);
          await completeLogin(result.user);
        } catch (err) {
          alert("ログインがキャンセルされたか、エラーが発生しました: " + err.message);
          btn.style.opacity = "1";
          btn.style.pointerEvents = "auto";
          btn.innerHTML = btnId === 'login-btn' ? '<i class="fab fa-google"></i> Googleログインして無料で試す' : 'Googleログイン';
        }
      });
    };

    setupLoginBtn("login-btn");
    setupLoginBtn("login-btn-header");
  </script>

  <!-- ============================================================
       DEMO LOADER SCRIPT
       ============================================================ -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const demoList = document.getElementById("nayuta-vj-list");
      const spinner = document.getElementById("vj-spinner");

      // APIからデモ用の最新記事を取得
      fetch("api/get_nayuta_vj.php")
        .then(res => res.json())
        .then(data => {
          if (spinner) spinner.remove();

          if (!data || data.length === 0) {
            demoList.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">現在、公開可能なデモ記事はありません。</p>';
            return;
          }

          data.forEach(p => {
            const card = document.createElement("div");
            card.className = "vj-demo-card";
            
            // 日付フォーマット
            const dateStr = p.date || "日付未設定";
            
            card.innerHTML = `
              <div>
                <div class="vj-card-header">
                  <img src="img/default-icon.png" alt="User Avatar" class="vj-avatar">
                  <div>
                    <div style="font-weight:700; color:#fff; font-size:0.9rem;">デモユーザー</div>
                    <div class="vj-meta">${dateStr}</div>
                  </div>
                </div>
                <h3 class="vj-card-title">${p.title}</h3>
                <p class="vj-card-desc">${p.summary}</p>
              </div>
              <span class="vj-card-tag">${p.category}</span>
            `;
            demoList.appendChild(card);
          });
        })
        .catch(err => {
          if (spinner) spinner.remove();
          demoList.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--warning-red);">読み込みエラーが発生しました。</p>';
          console.error("Failed to load VJ demo:", err);
        });
    });
  </script>
</body>
</html>