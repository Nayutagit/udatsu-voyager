<?php
// 🔹 開発環境判定の強化
$isLocal = (
    $_SERVER['HTTP_HOST'] === '127.0.0.1:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '192.168.') === 0
);
$cookieDomain = $isLocal ? '' : '.udatsu-voyager.com';
$cookieSecure = $isLocal ? false : true;

// 🔹 セッション設定（bootstrap.php と同期）
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure',   $cookieSecure ? '1' : '0');
if ($cookieDomain) {
    ini_set('session.cookie_domain', $cookieDomain);
}
ini_set('session.use_cookies',    '1');
ini_set('session.use_only_cookies','1');
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);

session_start();

// ✅ JSONで応答する明示
header('Content-Type: application/json; charset=UTF-8');

// ✅ 致命的エラーをキャッチしてJSONで返す
set_exception_handler(function($e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'サーバー内部エラー: ' . $e->getMessage()]);
  exit;
});
set_error_handler(function($errno, $errstr) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'エラー発生: ' . $errstr]);
  exit;
});

// 🔹 POSTデータを取得 (JSON or Form-encoded)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if ($input === null) {
    // JSONでない場合は通常の $_POST から取得
    $uid   = isset($_POST['uid']) ? trim((string)$_POST['uid']) : '';
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $name  = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
} else {
    $uid   = isset($input['uid']) ? trim((string)$input['uid']) : '';
    $email = isset($input['email']) ? trim((string)$input['email']) : '';
    $name  = isset($input['name']) ? trim((string)$input['name']) : '';
}

// 🔹 入力チェック
if (!$uid || !$email || !$name) {
  echo json_encode(['status' => 'error', 'message' => '必要な情報が不足しています', 'debug_received' => $rawInput]);
  exit;
}

// 🔹 Firestoreからプラン取得
require_once __DIR__ . '/../firebase/firestore.php';
$plan = getUserPlanFromFirestore($email);

// 🔹 取得したプランを正規化（null対策含む）
$plan = strtolower(trim((string)$plan));
$allowedPlans = ['trial', 'light', 'standard', 'premium', 'admin'];

if (!in_array($plan, $allowedPlans, true)) {
  echo json_encode(['status' => 'error', 'message' => 'Firestoreから有効なプランが取得できませんでした']);
  exit;
}

// 🔹 セッション保存
$_SESSION['uid']        = $uid;
$_SESSION['user_name']  = $name;
$_SESSION['user_plan']  = $plan;
$_SESSION['initiated']  = 1;

// 🔹 永続ログイン(30日間)用のCookie設定
$secret = 'udatsu_secret_2026_voyager';
$hash = hash_hmac('sha256', $uid . $name . $plan, $secret);
$cookiePayload = base64_encode(json_encode(['uid' => $uid, 'name' => $name, 'plan' => $plan, 'hash' => $hash]));

// 手動Set-CookieでLaxを確実に指定
$expires = gmdate('D, d M Y H:i:s T', time() + 2592000);
$domainPart = $cookieDomain ? '; Domain=' . $cookieDomain : '';
$securePart = $cookieSecure ? '; Secure' : '';
header('Set-Cookie: udatsu_auth=' . urlencode($cookiePayload) . '; Expires=' . $expires . '; Path=/' . $domainPart . $securePart . '; HttpOnly; SameSite=Lax');

// ✅ 成功レスポンス
echo json_encode(['status' => 'ok']);
exit;