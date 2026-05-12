<?php
/**
 * debug_auth.php — 一時診断ページ（確認後に削除してください）
 */

// bootstrap と同じセッション設定
$isLocal      = (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1:8000','localhost:8000']));
$cookieDomain = $isLocal ? '' : '.udatsu-voyager.com';
$cookieSecure = $isLocal ? '0' : '1';
ini_set('session.cookie_samesite',  'None');
ini_set('session.cookie_secure',    $cookieSecure);
if ($cookieDomain) ini_set('session.cookie_domain', $cookieDomain);
ini_set('session.use_cookies',      '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime',   2592000);
ini_set('session.cookie_lifetime',  2592000);
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>🔍 認証診断</title>
  <style>
    body { font-family: monospace; background:#111; color:#eee; padding:20px; }
    table { border-collapse:collapse; width:100%; }
    th, td { border:1px solid #444; padding:8px 12px; text-align:left; }
    th { background:#222; }
    .ok  { color:#4f4; }
    .ng  { color:#f44; }
    .warn{ color:#fa4; }
    h2   { color:#fcc; border-bottom:1px solid #555; padding-bottom:8px; }
  </style>
</head>
<body>
<h1>🔍 Udatsu 認証診断ページ</h1>
<p style="color:#aaa">診断後はこのファイル（debug_auth.php）を削除してください。</p>

<h2>1. セッション状態</h2>
<table>
  <tr><th>キー</th><th>値</th><th>状態</th></tr>
  <tr>
    <td>session_id</td>
    <td><?= htmlspecialchars(session_id()) ?></td>
    <td class="<?= session_id() ? 'ok' : 'ng' ?>"><?= session_id() ? '✓ 存在' : '✗ なし' ?></td>
  </tr>
  <tr>
    <td>$_SESSION['uid']</td>
    <td><?= htmlspecialchars($_SESSION['uid'] ?? '（未設定）') ?></td>
    <td class="<?= !empty($_SESSION['uid']) ? 'ok' : 'ng' ?>"><?= !empty($_SESSION['uid']) ? '✓ ログイン済み' : '✗ 未ログイン' ?></td>
  </tr>
  <tr>
    <td>$_SESSION['user_plan']</td>
    <td><?= htmlspecialchars($_SESSION['user_plan'] ?? '（未設定）') ?></td>
    <td><?= !empty($_SESSION['user_plan']) ? '✓' : '—' ?></td>
  </tr>
  <tr>
    <td>$_SESSION['user_name']</td>
    <td><?= htmlspecialchars($_SESSION['user_name'] ?? '（未設定）') ?></td>
    <td><?= !empty($_SESSION['user_name']) ? '✓' : '—' ?></td>
  </tr>
  <tr>
    <td>$_SESSION['initiated']</td>
    <td><?= htmlspecialchars($_SESSION['initiated'] ?? '（未設定）') ?></td>
    <td class="<?= !empty($_SESSION['initiated']) ? 'ok' : 'ng' ?>"><?= !empty($_SESSION['initiated']) ? '✓ 初期化済み' : '✗ 未初期化' ?></td>
  </tr>
</table>

<h2>2. Cookie 状態</h2>
<table>
  <tr><th>キー</th><th>値（冒頭50文字）</th></tr>
<?php if (empty($_COOKIE)): ?>
  <tr><td colspan="2" class="ng">Cookie が一切送られていません</td></tr>
<?php else: ?>
  <?php foreach ($_COOKIE as $k => $v): ?>
  <tr>
    <td><?= htmlspecialchars($k) ?></td>
    <td><?= htmlspecialchars(substr($v, 0, 60)) . (strlen($v) > 60 ? '...' : '') ?></td>
  </tr>
  <?php endforeach; ?>
<?php endif; ?>
</table>

<h2>3. 環境情報</h2>
<table>
  <tr><th>項目</th><th>値</th></tr>
  <tr><td>PHP バージョン</td><td><?= phpversion() ?></td></tr>
  <tr><td>HTTP_HOST</td><td><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') ?></td></tr>
  <tr><td>HTTPS</td>
    <td class="<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'ok' : 'ng' ?>">
      <?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '✓ HTTPS' : '✗ HTTP（Secure Cookie が効かない！）' ?>
    </td>
  </tr>
  <tr><td>session.cookie_samesite</td><td><?= ini_get('session.cookie_samesite') ?></td></tr>
  <tr><td>session.cookie_secure</td><td><?= ini_get('session.cookie_secure') ?></td></tr>
  <tr><td>session.cookie_domain</td><td><?= ini_get('session.cookie_domain') ?></td></tr>
  <tr><td>session.gc_maxlifetime</td><td><?= ini_get('session.gc_maxlifetime') ?></td></tr>
  <tr><td>session.save_path</td><td><?= htmlspecialchars(session_save_path()) ?></td></tr>
  <tr><td>REMOTE_ADDR</td><td><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></td></tr>
</table>

<h2>4. 最新ログ（直近10行）</h2>
<pre style="background:#1a1a1a; padding:15px; border-radius:8px; overflow-x:auto; color:#adf;"><?php
$logFile = __DIR__ . '/logs/auth_debug_' . date('Ymd') . '.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    echo htmlspecialchars(implode('', array_slice($lines, -10)));
} else {
    echo "ログファイルが存在しません: " . $logFile;
}
?></pre>

<p style="margin-top:30px; color:#888;">⚠️ この診断ページは確認後すぐ削除してください。</p>
</body>
</html>
