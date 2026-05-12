<?php
/**
 * Udatsu Voyager : core/bootstrap.php
 * 全ページで require_once する共通ブートストラップ
 * セッション初期化・プラン制限などを安全に管理する
 */

/* ───────────────
   セッション & Cookie
──────────────── */
// 🔹 開発環境判定の強化
$isLocal = (
    $_SERVER['HTTP_HOST'] === '127.0.0.1:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost:8000' || 
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '192.168.') === 0
);

// 🔹 セッション設定の刷新
session_name('UDATSU_SESS_V2');

if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 2592000,
            'path'     => '/',
            'domain'   => '', // ホストに限定して競合回避
            'secure'   => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        // PHP 7.3未満の互換処理
        session_set_cookie_params(2592000, '/; SameSite=Lax', '', $cookieSecure, true);
    }
    session_start();
    
    if (empty($_SESSION['initiated'])) {
        $_SESSION['initiated'] = 1;
    }
}

// 🔹 キャッシュ制御 (ログイン状態の誤認防止)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
$userPlan  = (isset($_SESSION['user_plan']) && $_SESSION['user_plan'] !== '') ? $_SESSION['user_plan'] : 'guest';

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