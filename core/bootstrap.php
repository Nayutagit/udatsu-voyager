<?php
/**
 * Udatsu Voyager : core/bootstrap.php
 * 全ページで require_once する共通ブートストラップ
 * セッション初期化・プラン制限などを安全に管理する
 */

/* ───────────────
   セッション & Cookie
──────────────── */
$isLocal = ($_SERVER['HTTP_HOST'] === '127.0.0.1:8000' || $_SERVER['HTTP_HOST'] === 'localhost:8000');
$cookieDomain = $isLocal ? '' : '.udatsu-voyager.com';
$cookieSecure = $isLocal ? '0' : '1';

ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure',  $cookieSecure);
if ($cookieDomain) {
    ini_set('session.cookie_domain', $cookieDomain);
}
ini_set('session.use_cookies',    '1');
ini_set('session.use_only_cookies','1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();

    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = 1;
        session_write_close();
        session_start();
    }
}

// 🔹 永続的ログイン(Cookie)の復元
if (empty($_SESSION['uid']) && !empty($_COOKIE['udatsu_auth'])) {
    $cookieData = json_decode(base64_decode($_COOKIE['udatsu_auth']), true);
    if (is_array($cookieData) && isset($cookieData['uid'], $cookieData['name'], $cookieData['plan'], $cookieData['hash'])) {
        $secret = 'udatsu_secret_2026_voyager';
        $expectedHash = hash_hmac('sha256', $cookieData['uid'] . $cookieData['name'] . $cookieData['plan'], $secret);
        if (hash_equals($expectedHash, $cookieData['hash'])) {
            $_SESSION['uid']       = $cookieData['uid'];
            $_SESSION['user_name'] = $cookieData['name'];
            $_SESSION['user_plan'] = $cookieData['plan'];
        } else {
            // 不正なCookieの場合は削除
            setcookie('udatsu_auth', '', time() - 3600, "/");
        }
    }
}

/* ───────────────
   設定ファイル / ライブラリ
──────────────── */
require_once __DIR__ . '/../config/plan_limits.php';   // プラン制限配列
require_once __DIR__ . '/../vendor/autoload.php';      // Composer オートローダ
require_once __DIR__ . '/functions.php';               // Firestore 関数など

/* ───────────────
   ユーザー情報の取得（安全に）
──────────────── */
$uid       = $_SESSION['uid']        ?? null;
$userName  = $_SESSION['user_name']  ?? 'ゲストさん';
$userPlan  = $_SESSION['user_plan']  ?? 'guest';

/**
 * 🚫 Firestore は set_session.php 内で行うべき！
 * bootstrap.php では必ず「セッションのみに基づいて処理」してください。
 */

/* ───────────────
   プラン別制限値
──────────────── */
$planLimits   = $plan_limits[$userPlan] ?? $plan_limits['guest'];
$dailyLimit   = $planLimits['daily_uses'];
$maxSeconds   = $planLimits['duration'];
$maxSizeMB    = ceil($maxSeconds / 60);
$maxSizeBytes = $maxSizeMB * 1024 * 1024;

/* ───────────────
   グローバル関数
──────────────── */
function getCurrentUserPlan(): string { return $GLOBALS['userPlan']; }
function getDailyUploadLimit(): int   { return $GLOBALS['dailyLimit']; }
function getMaxUploadBytes(): int     { return $GLOBALS['maxSizeBytes']; }
function getUserName(): string        { return $GLOBALS['userName']; }