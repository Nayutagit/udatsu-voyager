<?php
session_start();

// 管理者の認証情報（本番では.envなどで保護推奨）
$adminEmail = 'udatsuageteko@gmail.com';
$adminPassword = 'Udatsuagete5'; // 仮のパスワード

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  if ($email === $adminEmail && $password === $adminPassword) {
    $_SESSION['is_admin_authenticated'] = true;
    header('Location: admin_index.php');
    exit;
  } else {
    $error = 'メールアドレスまたはパスワードが違います。';
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>管理者ログイン</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: sans-serif;
      padding: 2em;
      background: #fefbe0;
      text-align: center;
    }
    h1 {
      margin-bottom: 1em;
    }
    form {
      display: inline-block;
      background: #fff;
      padding: 2em;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    input[type="email"],
    input[type="password"] {
      width: 250px;
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 1em;
    }
    button {
      padding: 10px 20px;
      font-size: 1em;
      background: #006888;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    .error {
      color: red;
      margin-bottom: 1em;
    }
  </style>
</head>
<body>
  <h1>🔐 管理者ログイン</h1>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div>
      <input type="email" name="email" placeholder="メールアドレス" required>
    </div>
    <div>
      <input type="password" name="password" placeholder="パスワード" required>
    </div>
    <div>
      <button type="submit">ログイン</button>
    </div>
  </form>
</body>
</html>