<?php
session_start();

// 🔧 ログファイルを使って通過を確認
file_put_contents(__DIR__ . '/callback_log.txt', "Reached at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Firebase SDK 初期化（$auth）
require_once __DIR__ . '/../firebase/firebase_admin.php';

// ↓ フロントで Firebase 認証チェックを実行する
echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン確認中...</title>
  <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
  <style>
    body {
      font-family: sans-serif;
      text-align: center;
      padding-top: 80px;
      background: #fefbe0;
    }
  </style>
</head>
<body>
  <h2>🔄 Googleログイン処理中...</h2>
  <p id="status">しばらくお待ちください</p>

  <script>
    const firebaseConfig = {
      apiKey: "AIzaSyBU9yT3jiTo2FlkHF-nQH3mCEa-VqvZnuY",
      authDomain: "udatsu-ageteko.firebaseapp.com"
    };
    firebase.initializeApp(firebaseConfig);
    const auth = firebase.auth();

    auth.getRedirectResult()
      .then(result => {
        if (!result.user) {
          document.getElementById('status').textContent = "⚠ ユーザーが取得できませんでした";
          throw new Error("ユーザー情報なし");
        }
        document.getElementById('status').textContent = "✅ Firebaseログイン成功、トークン取得中...";
        return result.user.getIdToken();
      })
      .then(token => {
        return fetch("verify_token.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ token })
        });
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('status').textContent = "✅ 管理者としてログイン中...リダイレクトします";
          window.location.href = "admin_index.php";
        } else {
          document.getElementById('status').textContent = "❌ 認証失敗: " + (data.error || "不明な理由");
          console.error("verify_token response:", data);
        }
      })
      .catch(err => {
        document.getElementById('status').textContent = "❌ エラー: " + err.message;
        console.error("redirect_callback error:", err);
      });
  </script>
</body>
</html>
HTML;