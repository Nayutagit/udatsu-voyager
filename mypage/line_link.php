<?php
require_once __DIR__ . '/../core/bootstrap.php';

$linkToken = $_GET['token'] ?? '';
if (empty($linkToken)) {
    die("無効なリンクです。LINEから再度連携URLを発行してください。");
}

$linkDataFile = __DIR__ . '/../users/line_links.json';
$links = file_exists($linkDataFile) ? json_decode(file_get_contents($linkDataFile), true) : [];

if (!isset($links[$linkToken])) {
    die("期限切れか無効なリンクです。再度LINEで「連携」とメッセージを送ってください。");
}

$lineUserId = $links[$linkToken]['line_user_id'];

// If the user isn't logged in, display the login flow, similar to index.php
if (empty($uid)) {
    $mode = 'login';
} else {
    // Already logged in, just complete the linking
    $mode = 'link';
    
    // Save mapping
    $mapFile = __DIR__ . '/../users/line_map.json';
    $map = file_exists($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];
    $map[$lineUserId] = $uid;
    file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
    
    // Remove the used token
    unset($links[$linkToken]);
    file_put_contents($linkDataFile, json_encode($links, JSON_PRETTY_PRINT));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>LINE連携 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
</head>
<body style="min-height: 100vh; display:flex; align-items:center; justify-content:center; background: var(--bg-dark);">
  <div class="container" style="max-width: 400px; text-align: center;">

    <?php if ($mode === 'login'): ?>
      <div class="glass-card" style="padding: 2rem;">
        <i class="fab fa-line" style="font-size: 3rem; color: #06C755; margin-bottom: 1rem;"></i>
        <h2 style="margin-bottom: 1rem;">UdatsuとLINEを連携</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">
          LINEから音声をアップロードできるようにするため、Udatsuアカウントにログインしてください。
        </p>
        <button id="login-btn" class="btn btn-primary" style="width: 100%; padding: 15px;">
          <i class="fab fa-google"></i> Google連携でログイン
        </button>
      </div>

      <!-- Firebase JS Setup -->
      <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
        import { getFirestore, doc, getDoc, setDoc } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

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
            document.getElementById("login-btn").innerHTML = '<i class="fas fa-spinner fa-spin"></i> 処理中...';
            const result = await signInWithPopup(auth, provider);
            const user = result.user;

            const docRef = doc(db, "users", user.email);
            const docSnap = await getDoc(docRef);
            let plan = "trial";
            if (!docSnap.exists()) {
              await setDoc(docRef, { uid: user.uid, email: user.email, name: user.displayName, plan: "trial", createdAt: new Date() });
            } else if (docSnap.data().plan) {
              plan = docSnap.data().plan;
            }

            await fetch("../save_plan.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ email: user.email, plan: plan, name: user.displayName, uid: user.uid })
            });

            // セッションが作られたら自身をリロードしてリンク処理を走らせる
            location.reload();
          } catch (e) {
            alert("ログインエラー: " + e.message);
            document.getElementById("login-btn").innerHTML = '<i class="fab fa-google"></i> Google連携でログイン';
          }
        });
      </script>

    <?php else: ?>
      <!-- 連携完了画面 -->
      <div class="glass-card" style="padding: 2rem; border-color: #06C755;">
        <i class="fas fa-check-circle" style="font-size: 3rem; color: #06C755; margin-bottom: 1rem;"></i>
        <h2 style="margin-bottom: 1rem;">連携が完了しました！</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; line-height:1.6;">
          Udatsu VoyagerとLINEの紐付けが完了しました。<br>
          この画面を閉じてLINEに戻り、ボイスメッセージや音声ファイルを送ってみてください。<br>自動で解析が始まります🚀
        </p>
        <a href="mypage.php" class="btn btn-secondary"><i class="fas fa-home"></i> ダッシュボードへ</a>
      </div>
    <?php endif; ?>

  </div>
</body>
</html>
