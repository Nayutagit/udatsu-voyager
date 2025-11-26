<?php
require_once __DIR__ . '/core/bootstrap.php';

if ($userPlan !== 'guest') {
  header("Location: voyager_upload.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu - ログイン</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <img src="img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
  <h1>Udatsu にログインしよう</h1>
  <div class="subtitle">音声文字起こしツール「Udatsu Voyager」もここから使えます</div>

  <div class="login-box">
    <div class="lead-message">
      あなたの「声」が未来をつくる。<br>
      記事作成も音声解析も、ここからスタート！<br>
      <strong>あなたのうだつを上げる第一歩です。</strong>
    </div>

    <button id="login-btn">
      <img src="img/google-icon.png" alt="Google" width="20" height="20" style="margin-right: 10px;">
      Googleではじめる
    </button>

    <div class="small-note">
      🔐 ご利用には Googleアカウントが必要です。<br>
      💡 Safari または Chrome からご利用ください。
    </div>
  </div>

  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
    import {
      getAuth,
      signInWithPopup,
      GoogleAuthProvider
    } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
    import {
      getFirestore,
      doc,
      getDoc,
      setDoc
    } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

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

    document.getElementById("login-btn").addEventListener("click", async () => {
      try {
        // ローディング状態開始
        const btn = document.getElementById("login-btn");
        btn.style.opacity = "0.7";
        btn.style.pointerEvents = "none";
        btn.innerHTML = "ログイン中...";
        
        const result = await signInWithPopup(auth, provider);
        const user = result.user;
        const userName = user.displayName;
        const userEmail = user.email;
        const uid = user.uid;

        const docRef = doc(db, "users", userEmail);
        const docSnap = await getDoc(docRef);

        // 初めての人なら Firestore に登録
        if (!docSnap.exists()) {
          await setDoc(docRef, {
            uid: uid,
            email: userEmail,
            name: userName,
            plan: "trial",
            createdAt: new Date()
          });
        }

        // プラン取得
        let plan = "trial";
        const finalSnap = await getDoc(docRef);
        if (finalSnap.exists() && finalSnap.data().plan) {
          plan = finalSnap.data().plan;
        }

        // PHP にも送信してセッション保存
        await fetch("save_plan.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email: userEmail, plan, name: userName, uid })
        });

        // 少し待ってからリダイレクト
        setTimeout(() => {
          location.href = "voyager_upload.php";
        }, 500);

      } catch (error) {
        const btn = document.getElementById("login-btn");
        btn.style.opacity = "1";
        btn.style.pointerEvents = "auto";
        btn.innerHTML = '<img src="img/google-icon.png" alt="Google" width="20" height="20" style="margin-right: 10px;"> Googleではじめる';
        alert("ログイン失敗: " + error.message);
      }
    });

    // ページロード時のアニメーション
    window.addEventListener('load', () => {
      // ロゴにクリックでスピン効果
      document.querySelector('.logo').addEventListener('click', function() {
        this.style.transition = 'transform 1s ease';
        this.style.transform = 'rotate(360deg)';
        setTimeout(() => {
          this.style.transform = 'rotate(0deg)';
        }, 1000);
      });
    });
  </script>

</body>
</html>