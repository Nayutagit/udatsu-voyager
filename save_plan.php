<?php
/**
 * save_plan.php  ─ 緊急修正版 (PHP 5.6+ 互換)
 * Firebase 認証後にPHPセッションを確立するエンドポイント
 */

// ─── 1. セッション設定（bootstrap.php と完全同一）─────────────────────────
$isLocal      = (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1:8000','localhost:8000']));
$cookieDomain = $isLocal ? '' : '.udatsu-voyager.com';
$cookieSecure = $isLocal ? '0' : '1';

ini_set('session.cookie_samesite',  'None');
ini_set('session.cookie_secure',    $cookieSecure);
if ($cookieDomain) {
    ini_set('session.cookie_domain', $cookieDomain);
}
ini_set('session.use_cookies',      '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime',   2592000);
ini_set('session.cookie_lifetime',  2592000);

session_start();

// ─── 2. レスポンスヘッダー ──────────────────────────────────────────────────
header('Content-Type: application/json');

// ─── 3. 診断ログ ──────────────────────────────────────────────────────────
$logDir = __DIR__ . '/logs/';
if (!file_exists($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . 'auth_debug_' . date('Ymd') . '.log';

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
$data  = json_decode(file_get_contents('php://input'), true);
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

@file_put_contents($logFile, '[SESSION_SET] session_id=' . session_id() . ' uid=' . $uid . PHP_EOL, FILE_APPEND);

// ─── 6. 永続Cookie（PHP互換のheader()方式）──────────────────────────────
$secret      = 'udatsu_secret_2026_voyager';
$authHash    = hash_hmac('sha256', $uid . $name . $plan, $secret);
$cookieValue = base64_encode(json_encode(['uid' => $uid, 'name' => $name, 'plan' => $plan, 'hash' => $authHash]));
$expires     = gmdate('D, d M Y H:i:s T', time() + 2592000);
$domainPart  = $cookieDomain ? '; Domain=' . $cookieDomain : '';
$securePart  = $cookieSecure ? '; Secure' : '';
header('Set-Cookie: udatsu_auth=' . urlencode($cookieValue) . '; Expires=' . $expires . '; Path=/' . $domainPart . $securePart . '; HttpOnly; SameSite=None');

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

echo json_encode(['status' => 'ok', 'plan' => $plan]);
