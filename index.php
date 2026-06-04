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
  <title>Udatsu</title>
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
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      justify-content: center;
      align-items: center;
      background-color: var(--bg-primary);
      padding: var(--spacing-lg);
    }
    .login-container {
      width: 100%;
      max-width: 400px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .hero-logo {
      width: 80px;
      height: 80px;
      border-radius: 20px;
      margin-bottom: var(--spacing-lg);
      box-shadow: 0 4px 20px rgba(252, 200, 0, 0.2);
    }
    .brand-title {
      font-family: var(--font-display);
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: var(--spacing-xs);
      letter-spacing: -0.02em;
    }
    .brand-subtitle {
      color: var(--text-secondary);
      font-size: 0.95rem;
      margin-bottom: var(--spacing-2xl);
    }
    .login-box {
      width: 100%;
      background: var(--bg-secondary);
      border-radius: var(--radius-lg);
      padding: var(--spacing-xl) var(--spacing-lg);
      border: 1px solid var(--border-color);
    }
    .login-box p {
      color: var(--text-secondary);
      font-size: 0.9rem;
      margin-bottom: var(--spacing-xl);
      line-height: 1.5;
    }
    .btn-google {
      width: 100%;
      padding: 14px;
      border-radius: 24px;
      background: var(--accent-color);
      color: var(--bg-primary);
      font-weight: 600;
      font-size: 1rem;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    .btn-google:active {
      opacity: 0.8;
    }
    .secure-badge {
      margin-top: var(--spacing-lg);
      font-size: 0.75rem;
      color: var(--text-tertiary);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .footer-links {
      margin-top: var(--spacing-2xl);
      font-size: 0.75rem;
      color: var(--text-tertiary);
    }
    .footer-links a {
      color: var(--text-tertiary);
      text-decoration: none;
      margin: 0 10px;
    }
  </style>
</head>
<body>

  <div class="login-container animate-fadeup">
    
    <img src="img/udatsu-logo.png" alt="Udatsu Logo" class="hero-logo">
    <h1 class="brand-title">Udatsu</h1>
    <p class="brand-subtitle">声でつながる、新しいSNS。</p>

    <div class="login-box">
      <p>
        AIがあなたの音声をテキスト化し、<br>
        自動で要約・記事化します。
      </p>

      <button id="login-btn" class="btn-google">
        <i class="fab fa-google"></i> Googleでログイン
      </button>

      <div class="secure-badge">
        <i class="fas fa-lock"></i> Secure Login via Firebase
      </div>
    </div>

    <div class="footer-links">
      <div style="margin-bottom: 10px;">
        <a href="terms.php">利用規約</a> | 
        <a href="privacy.php">プライバシーポリシー</a>
      </div>
      &copy; 2026 Nyct Studio
    </div>

  </div>

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

    document.getElementById("login-btn").addEventListener("click", async () => {
      const btn = document.getElementById("login-btn");
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
        btn.innerHTML = 'Googleでログイン';
      }
    });
  </script>
</body>
</html>