<?php
session_start();

// 🔐 管理者ログインチェック
if (empty($_SESSION['is_admin_authenticated'])) {
  header('Location: admin_login.php');
  exit;
}

$uid = $_GET['uid'] ?? '';
$index = $_GET['index'] ?? '';
$saved = $_GET['saved'] ?? '';

if (!$uid || !is_numeric($index)) {
  echo "不正なアクセスです。";
  exit;
}

$userDir = __DIR__ . '/../users/';
$postFile = $userDir . $uid . '_posts.json';
$profileFile = $userDir . $uid . '_profile.json';

if (!file_exists($postFile)) {
  echo "投稿が見つかりません。";
  exit;
}

$posts = json_decode(file_get_contents($postFile), true);
$post = $posts[$index] ?? null;

if (!$post) {
  echo "指定された投稿が存在しません。";
  exit;
}

$profile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];
$displayName = $profile['display_name'] ?? '（名前未設定）';
$email = $profile['email'] ?? '（アドレス未設定）';

// 管理者編集内容の取得（初回は原文コピー）
$adminEdit = $post['admin_edit'] ?? [];
$adminBody = $adminEdit['body'] ?? ($post['body'] ?? $post['text'] ?? '');
$adminNote = $adminEdit['note'] ?? '';
$udarowStatus = !empty($adminEdit['status']);
$udarowUrl = $adminEdit['url'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>投稿詳細 - 管理パネル</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;500;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'M PLUS Rounded 1c', 'Meiryo', sans-serif;
      background: #fffef5;
      padding: 20px;
    }
    h1 { font-size: 1.5em; margin-bottom: 0.5em; }
    .meta { font-size: 0.9em; color: #555; margin-bottom: 1em; }
    pre {
      background: #fff;
      padding: 15px;
      border: 1px solid #ccc;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
    .edit-box, .note-box {
      width: 100%;
      height: 150px;
      margin-top: 1em;
      padding: 12px;
      border: 1px solid #888;
      font-family: inherit;
      font-size: 1em;
      background: #fff;
    }
    form button, .preview-btn {
      margin-top: 10px;
      background: #00aa88;
      color: #fff;
      border: none;
      padding: 10px 20px;
      font-size: 0.9em;
      border-radius: 4px;
      cursor: pointer;
    }
    .back-link {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      color: #0077cc;
      font-size: 0.9em;
    }
    .alert {
      padding: 10px 15px;
      background: #d9f9e2;
      border: 1px solid #00aa88;
      color: #006644;
      border-radius: 4px;
      margin-bottom: 1em;
    }
    .form-group {
      margin-top: 1em;
    }
  </style>
</head>
<body>
  <h1>📖 投稿詳細</h1>

  <?php if ($saved): ?>
    <div class="alert">✅ 編集内容を保存しました</div>
  <?php endif; ?>

  <div class="meta">
    <strong>ユーザー名：</strong><?= htmlspecialchars($displayName) ?><br>
    <strong>メールアドレス：</strong><?= htmlspecialchars($email) ?><br>
    <strong>投稿タイトル：</strong><?= htmlspecialchars($post['title'] ?? '（無題）') ?><br>
    <strong>投稿日時：</strong><?= htmlspecialchars($post['created'] ?? $post['date'] ?? '不明') ?><br>
    <strong>ステータス：</strong><?= htmlspecialchars($post['status'] ?? '未設定') ?><br>
  </div>
  <h2>📝 ユーザー原文</h2>
  <pre><?= htmlspecialchars($post['body'] ?? $post['text'] ?? '（本文なし）') ?></pre>

  <h2>✍ 管理者編集欄</h2>
  <form method="POST" action="save_admin_edit.php">
    <textarea name="edited_body" class="edit-box"><?= htmlspecialchars($adminBody) ?></textarea>

    <div class="form-group">
      <label><strong>🗒 管理者メモ：</strong></label><br>
      <textarea name="admin_note" class="note-box"><?= htmlspecialchars($adminNote) ?></textarea>
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="udarow_status" value="1" <?= $udarowStatus ? 'checked' : '' ?>> 📢 Udarow!掲載中にする</label>
    </div>

    <div class="form-group">
      <label><strong>🔗 Udarow!掲載URL：</strong></label><br>
      <input type="text" name="udarow_url" value="<?= htmlspecialchars($udarowUrl) ?>" style="width:80%; padding:6px; font-size:0.9em;">
      <a href="#" target="_blank" onclick="window.open(this.previousElementSibling.value, '_blank'); return false;" class="preview-btn">👁 プレビュー</a>
    </div>

    <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
    <input type="hidden" name="index" value="<?= $index ?>">
    <br>
    <button type="submit">💾 編集内容を保存</button>
  </form>

  <a href="admin_index.php" class="back-link">← 一覧に戻る</a>
</body>
</html>