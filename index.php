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
  <title>Udatsu - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="manifest" href="manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Voyager">
  <link rel="apple-touch-icon" href="img/udatsu-logo.png">
  <style>
    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      justify-content: center;
      align-items: center;
      background-image: url('img/concrete_bg.png');
      background-size: cover;
      background-position: center;
    }
    .hero-logo {
      width: 120px;
      margin-bottom: 20px;
      filter: drop-shadow(0 0 15px rgba(252, 200, 0, 0.6));
      animation: float 6s ease-in-out infinite;
    }
    @keyframes float {
      0% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
      100% { transform: translateY(0px); }
    }
  </style>
</head>
<body>

  <div class="container" style="text-align: center; max-width: 500px;">
    
    <div class="animate-fadeup">
      <img src="img/udatsu-logo.png" alt="Udatsu Logo" class="hero-logo">
      <h1 class="text-neon" style="font-size: 3rem; margin-bottom: 10px; letter-spacing: 0.1em;">UDATSU</h1>
      <p class="text-muted" style="font-family: var(--font-serif); letter-spacing: 0.2em; margin-bottom: 40px;">VOICE & THOUGHT ASSETS</p>
    </div>

    <div class="glass-card animate-fadeup delay-100" style="padding: 40px;">
      <h2 style="font-size: 1.5rem; margin-bottom: 20px;">Welcome Back</h2>
      <p class="text-muted" style="margin-bottom: 30px; line-height: 1.6;">
        あなたの「声」が未来をつくる。<br>
        思考資産を蓄積し、うだつを上げよう。
      </p>

      <button id="login-btn" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">
        <i class="fab fa-google"></i> Googleでログイン
      </button>

      <div style="margin-top: 20px; font-size: 0.8rem; color: var(--text-muted);">
        <i class="fas fa-lock"></i> Secure Access via Firebase
      </div>
    </div>

    <div class="animate-fadeup delay-200" style="margin-top: 40px; font-size: 0.8rem; color: var(--text-muted);">
      <div style="margin-bottom: 10px;">
        <a href="terms.php" style="color: var(--text-muted); text-decoration: none; margin: 0 10px;">利用規約</a> | 
        <a href="privacy.php" style="color: var(--text-muted); text-decoration: none; margin: 0 10px;">プライバシーポリシー</a>
      </div>
      &copy; 2026 Nyct Studio. All Rights Reserved.
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
      alert("【A】セッション保存開始: " + user.email);
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
        alert("【Redirect ERROR】" + error.message);
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
        alert("【Popup ERROR】" + err.message);
        btn.style.opacity = "1";
        btn.style.pointerEvents = "auto";
        btn.innerHTML = 'Googleでログイン';
      }
    });
    // Debug info
    window.showDebug = function() {
      const debugInfo = {
        cookies: document.cookie,
        href: location.href,
        userAgent: navigator.userAgent
      };
      alert("Debug Info:\n" + JSON.stringify(debugInfo, null, 2));
    };
  </script>

  <!-- Debug Trigger (Hidden) -->
  <div style="position:fixed; bottom:10px; right:10px; opacity:0.1; cursor:pointer;" onclick="showDebug()">.</div>

</body>
</html>