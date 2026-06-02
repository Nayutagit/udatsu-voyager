<?php
session_start();
$_SESSION['uid'] = 'YvD0Ab05RJZxSeNEAqAgurEzMEh2';
$_SESSION['user_name'] = '那由他';
$_SESSION['user_plan'] = 'premium';
$_SESSION['initiated'] = 1;

$secret = 'udatsu_secret_2026_voyager';
$hash = hash_hmac('sha256', $_SESSION['uid'] . $_SESSION['user_name'] . $_SESSION['user_plan'], $secret);
$cookiePayload = base64_encode(json_encode(['uid' => $_SESSION['uid'], 'name' => $_SESSION['user_name'], 'plan' => $_SESSION['user_plan'], 'hash' => $hash]));
setcookie('udatsu_auth', $cookiePayload, time() + 2592000, "/", '.udatsu-voyager.com');

header('Location: /mypage/mypage.php');
exit;
