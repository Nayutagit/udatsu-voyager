// ✅ Firebase + Popupでのシンプルログイン処理（firebase-app.js）

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.11.0/firebase-app.js";
import {
  getAuth,
  GoogleAuthProvider,
  signInWithPopup
} from "https://www.gstatic.com/firebasejs/10.11.0/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyBU9yT3jiTo2FlkHF-nQH3mCEa-VqvZnuY",
  authDomain: "udatsu-ageteko.firebaseapp.com",
  projectId: "udatsu-ageteko",
  storageBucket: "udatsu-ageteko.firebasestorage.app",
  messagingSenderId: "523211796022",
  appId: "1:523211796022:web:fb83d29ef10d94547a3297",
  measurementId: "G-2FJTMKBSH4"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

// ✅ ログイン関数
window.loginWithGoogle = () => {
  signInWithPopup(auth, provider)
    .then((result) => {
      const user = result.user;
      if (!user.email || !user.uid) {
        alert("ログイン情報が取得できませんでした");
        return;
      }

      const body = new URLSearchParams();
      body.append("email", user.email);
      body.append("name", user.displayName || "NoName");
      body.append("uid", user.uid);

      console.log("送信する内容:", body.toString());

      return fetch("set_session.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: body.toString()
      });
    })
    .then((res) => res.json())
    .then((data) => {
      if (data.status === "ok") {
        window.location.reload();
      } else {
        console.error("セッション保存失敗:", data);
        alert("セッション保存に失敗しました");
      }
    })
    .catch((error) => {
      console.error("ログイン失敗:", error);
      alert("Googleログインに失敗しました");
    });
};