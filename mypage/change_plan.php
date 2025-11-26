<?php
session_start();
require_once __DIR__ . '/../firebase/firestore.php';

// 🔹 セッションから情報取得
$email = $_SESSION['uid'] ?? '';
$new_plan = $_GET['plan'] ?? '';

// 🔹 許可されたプラン一覧（ここでは trial のみ切替許可）
$allowed_plans = ['trial'];

if (!$email || !in_array($new_plan, $allowed_plans, true)) {
  // 無効なアクセスはメンバーシップ画面に戻す
  header('Location: membership.php');
  exit;
}

// 🔹 Firestoreに保存
$success = saveUserPlanToFirestore($email, $new_plan);

// 🔹 セッション更新（Firestore保存に関係なく）
$_SESSION['user_plan'] = $new_plan;

// 🔹 Firestore保存結果で分岐
if ($success) {
  header('Location: success.php');
  exit;
} else {
  // エラーログに記録
  error_log("Firestoreへの保存に失敗しました: $email / $new_plan");

  // ユーザーにはやさしい案内
  $_SESSION['plan_change_notice'] = "プランの反映に少し時間がかかる場合があります。<br>しばらくしてからマイページをご確認ください。";
  header('Location: success.php');
  exit;
}