<?php
/**
 * save_plan.php
 * Firebase 認証後にPHPセッションを確立するエンドポイント
 * ─ bootstrap.php のセッション設定（SameSite=None; Secure）を明示的に適用
 * ─ 永続的な認証Cookie も設定してセッション断絶を防止
 */

// ─── 1. セッション設定（bootstrap.php と同一設定を手動適用）────────────────
$isLocal      = (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['127.0.0.1:8000', 'localhost:8000']));
$cookieDomain = $isLocal ? '' : '.udatsu-voyager.com';
$cookieSecure = $isLocal ? '0' : '1';

ini_set('session.cookie_samesite',  'None');
ini_set('session.cookie_secure',    $cookieSecure);
if ($cookieDomain) {
    ini_set('session.cookie_domain', $cookieDomain);
}
ini_set('session.use_cookies',       '1');
ini_set('session.use_only_cookies',  '1');
ini_set('session.gc_maxlifetime',    2592000); // 30日
ini_set('session.cookie_lifetime',   2592000);

session_start();

// ─── 2. レスポンスヘッダー ──────────────────────────────────────────────────
header('Content-Type: application/json');

// CORS（海外アクセス対応）
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://udatsu-voyager.com',
    'https://www.udatsu-voyager.com',
    'http://127.0.0.1:8000',
    'http://localhost:8000',
];
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── 3. 診断ログ（認証フロー追跡用）────────────────────────────────────────
$logDir  = __DIR__ . '/logs/';
if (!file_exists($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . 'auth_debug_' . date('Ymd') . '.log';

$diagInfo = [
    'timestamp'       => date('Y-m-d H:i:s'),
    'remote_ip'       => $_SERVER['REMOTE_ADDR']   ?? 'unknown',
    'http_origin'     => $_SERVER['HTTP_ORIGIN']   ?? 'none',
    'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'session_id_pre'  => session_id(),
    'session_existed' => !empty($_SESSION['uid']),
    'cookies_sent'    => array_keys($_COOKIE),
    'forwarded_for'   => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
];
file_put_contents($logFile, '[SAVE_PLAN START] ' . json_encode($diagInfo, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// ─── 4. 入力データのバリデーション ────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$email = trim($data['email'] ?? '');
$plan  = trim($data['plan']  ?? 'guest');
$name  = trim($data['name']  ?? 'ゲストさん');
$uid   = trim($data['uid']   ?? '');

file_put_contents($logFile, '[PAYLOAD] uid=' . $uid . ' plan=' . $plan . ' email=' . $email . PHP_EOL, FILE_APPEND);

if (!$uid || !$plan) {
    file_put_contents($logFile, '[ERROR] Invalid payload — uid or plan missing' . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => '不正なデータ']);
    exit;
}

// ─── 5. セッションに保存 ─────────────────────────────────────────────────
$_SESSION['uid']       = $uid;
$_SESSION['user_plan'] = $plan;
$_SESSION['user_name'] = $name;
$_SESSION['initiated'] = 1;

file_put_contents($logFile, '[SESSION_SET] session_id=' . session_id() . ' uid=' . $uid . PHP_EOL, FILE_APPEND);

// ─── 6. 永続的な認証Cookie を設定（セッション断絶フォールバック）────────────
$secret      = 'udatsu_secret_2026_voyager';
$authHash    = hash_hmac('sha256', $uid . $name . $plan, $secret);
$cookieValue = base64_encode(json_encode(['uid' => $uid, 'name' => $name, 'plan' => $plan, 'hash' => $authHash]));
$cookieOptions = [
    'expires'  => time() + 2592000, // 30日
    'path'     => '/',
    'secure'   => !$isLocal,
    'httponly' => true,
    'samesite' => 'None',
];
if ($cookieDomain) {
    $cookieOptions['domain'] = $cookieDomain;
}
setcookie('udatsu_auth', $cookieValue, $cookieOptions);

file_put_contents($logFile, '[COOKIE_SET] udatsu_auth issued for uid=' . $uid . PHP_EOL, FILE_APPEND);

// ─── 7. プロフィールファイルへ保存 ────────────────────────────────────────
$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);

$profileFile = $userDir . $uid . '_profile.json';
$profile     = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

if (empty($profile['display_name'])) $profile['display_name'] = $name;
if (empty($profile['email']))        $profile['email']        = $email;
if (empty($profile['plan']))         $profile['plan']         = $plan;
$profile['last_login'] = date('Y-m-d H:i:s');

file_put_contents($profileFile, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

session_write_close();

file_put_contents($logFile, '[DONE] Session closed OK' . PHP_EOL, FILE_APPEND);

echo json_encode(['status' => 'ok', 'plan' => $plan]);
