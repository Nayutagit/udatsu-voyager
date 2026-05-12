<?php
error_reporting(0);
ob_start();
/**
 * save_plan.php  ─ 緊急修正版
 * Firebase 認証後にPHPセッションを確立するエンドポイント
 */

// ─── 1. セッション設定（bootstrap.php と完全同一）─────────────────────────
$isLocal = (
    $_SERVER['HTTP_HOST'] === '127.0.0.1:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '192.168.') === 0
);
$cookieSecure = $isLocal ? false : true;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_writable(session_save_path())) {
    @file_put_contents(__DIR__ . '/session_error.log', date('Y-m-d H:i:s') . ' Session path not writable: ' . session_save_path() . PHP_EOL, FILE_APPEND);
}

// ─── 2. レスポンスヘッダー ──────────────────────────────────────────────────
header('Content-Type: application/json');

// ─── 3. 診断ログ ──────────────────────────────────────────────────────────
// ─── 3. 診断ログ ──────────────────────────────────────────────────────────
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
    @chmod($logDir, 0777);
}
$logFile = $logDir . '/auth_debug_' . date('Ymd') . '.log';

$diagInfo = [
    'timestamp'   => date('Y-m-d H:i:s'),
    'remote_ip'   => $_SERVER['REMOTE_ADDR']    ?? 'unknown',
    'http_origin' => $_SERVER['HTTP_ORIGIN']    ?? 'none',
    'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
    'session_id'  => session_id(),
    'cookies'     => array_keys($_COOKIE),
];
@file_put_contents($logFile, '[START] ' . json_encode($diagInfo, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// ─── 4. バリデーション ────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$data     = json_decode($rawInput, true);

if ($data === null) {
    // JSONでない場合は通常の $_POST から取得
    $data = [
        'email' => $_POST['email'] ?? '',
        'plan'  => $_POST['plan']  ?? '',
        'name'  => $_POST['name']  ?? '',
        'uid'   => $_POST['uid']   ?? ''
    ];
}

$email = trim($data['email'] ?? '');
$plan  = trim($data['plan']  ?? 'guest');
$name  = trim($data['name']  ?? 'ゲストさん');
$uid   = trim($data['uid']   ?? '');

@file_put_contents($logFile, '[PAYLOAD] uid=' . $uid . ' plan=' . $plan . PHP_EOL, FILE_APPEND);

if (!$uid || !$plan) {
    @file_put_contents($logFile, '[ERROR] uid or plan missing' . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => '不正なデータ']);
    exit;
}

// ─── 5. セッションに保存 ─────────────────────────────────────────────────
$_SESSION['uid']       = $uid;
$_SESSION['user_plan'] = $plan;
$_SESSION['user_name'] = $name;
$_SESSION['initiated'] = 1;

@file_put_contents($logFile, '[SESSION_SET] session_id=' . session_id() . ' uid=' . $uid . ' plan=' . $plan . PHP_EOL, FILE_APPEND);
@file_put_contents($logFile, '[SERVER_INFO] HTTPS=' . ($_SERVER['HTTPS'] ?? 'off') . ' HOST=' . ($_SERVER['HTTP_HOST'] ?? 'none') . PHP_EOL, FILE_APPEND);

// ─── 6. 永続Cookie（PHP互換のheader()方式）──────────────────────────────
$secret      = 'udatsu_secret_2026_voyager';
$authHash    = hash_hmac('sha256', $uid . $name . $plan, $secret);
$cookieValue = base64_encode(json_encode(['uid' => $uid, 'name' => $name, 'plan' => $plan, 'hash' => $authHash]));
$expires     = gmdate('D, d M Y H:i:s T', time() + 2592000);
$domainPart  = $cookieDomain ? '; Domain=' . $cookieDomain : '';
$securePart  = $cookieSecure ? '; Secure' : '';
header('Set-Cookie: udatsu_auth=' . urlencode($cookieValue) . '; Expires=' . $expires . '; Path=/' . $domainPart . $securePart . '; HttpOnly; SameSite=Lax');

@file_put_contents($logFile, '[COOKIE_SET] ok' . PHP_EOL, FILE_APPEND);

// ─── 7. プロフィールファイル ──────────────────────────────────────────────
$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) @mkdir($userDir, 0755, true);

$profileFile = $userDir . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

if (empty($profile['display_name'])) $profile['display_name'] = $name;
if (empty($profile['email']))        $profile['email']        = $email;
if (empty($profile['plan']))         $profile['plan']         = $plan;
$profile['last_login'] = date('Y-m-d H:i:s');

@file_put_contents($profileFile, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

session_write_close();

@file_put_contents($logFile, '[DONE] ok' . PHP_EOL, FILE_APPEND);

ob_clean();
echo json_encode(['status' => 'ok', 'plan' => $plan]);
exit();
