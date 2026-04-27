<?php
session_start();
if (isset($_SESSION['uid'])) {
  header("Location: mypage.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Udatsu｜はじめての方へ</title>
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
      padding: 40px 20px;
      text-align: center;
    }
    .logo {
      width: 160px;
      margin-bottom: 2rem;
      filter: drop-shadow(0 0 15px rgba(252, 200, 0, 0.6));
      animation: logoGlow 3s ease-in-out infinite alternate;
    }
    @keyframes logoGlow {
      from { filter: drop-shadow(0 0 10px rgba(252, 200, 0, 0.4)); transform: scale(1); }
      to { filter: drop-shadow(0 0 25px rgba(252, 200, 0, 0.8)); transform: scale(1.05); }
    }
    h1 {
      font-size: 2rem;
      margin-bottom: 1.5rem;
      color: var(--text-white);
      font-weight: 700;
      text-shadow: 0 0 10px rgba(252, 200, 0, 0.5);
    }
    p {
      font-size: 1.1rem;
      margin-bottom: 3rem;
      max-width: 600px;
      color: var(--text-muted);
      line-height: 1.8;
    }
    #login-btn {
      background: linear-gradient(45deg, var(--primary-neon), #ffda44);
      color: #000;
      border: none;
      padding: 1rem 2.5rem;
      font-size: 1.1rem;
      border-radius: 50px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 15px;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
      font-weight: 700;
      transition: all 0.3s ease;
    }
    #login-btn:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
    }
    #login-btn img {
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }
  </style>
</head>
<body>
  <img src="../img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
  <h1>ようこそ Udatsuへ！</h1>
  <p>Udatsuは「声を使って思考を整理し、発信を後押しする」プラットフォームです。<br>まずはGoogleアカウントでログインして、あなたのマイページを作りましょう。</p>

  <button id="login-btn">
    <img src="../img/google-icon.png" alt="Googleアイコン" width="24" height="24">
    Googleで始める
  </button>

  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
    import { getAuth, signInWithPopup, GoogleAuthProvider } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
    import { getFirestore, doc, getDoc } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

    const firebaseConfig = {
      apiKey: "AIzaSyBU9yT3jiTo2FlkHF-nQH3mCEa-VqvZnuY",
      authDomain: "udatsu-ageteko.firebaseapp.com",
      projectId: "udatsu-ageteko",
      storageBucket: "udatsu-ageteko.appspot.com",
      messagingSenderId: "523211796022",
      appId: "1:523211796022:web:fb83d29ef10d94547a3297"
    };

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);
    const provider = new GoogleAuthProvider();

    document.getElementById("login-btn").addEventListener("click", async () => {
      try {
        const result = await signInWithPopup(auth, provider);
        const user = result.user;
        const uid = user.uid;
        const email = user.email;
        const name = user.displayName;

        const docRef = doc(db, "users", email);
        const docSnap = await getDoc(docRef);
        let plan = "free";
        if (docSnap.exists()) {
          plan = docSnap.data().plan || "free";
        }

        await fetch("../save_plan.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ uid, email, name, plan })
        });

        window.location.href = "mypage.php";
      } catch (error) {
        alert("ログイン失敗：" + error.message);
      }
    });
  </script>
</body>
</html>