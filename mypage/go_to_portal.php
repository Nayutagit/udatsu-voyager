<?php
session_start();

// ✅ 正しいパスに修正
require_once __DIR__ . '/../vendor/autoload.php';           // public_html/vendor/autoload.php
require_once __DIR__ . '/../../firebase/firestore.php';    // firebase/firestore.php

// ✅ Stripe秘密キー（本番用に置き換えてね）
\Stripe\Stripe::setApiKey('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'); // ← あなたの本番キーに変更

// ✅ ログイン中のユーザーのメールを取得
$email = $_SESSION['email'] ?? '';
if (!$email) {
  header('Location: membership.php');
  exit;
}

// ✅ FirestoreからStripeのCustomer IDを取得
$customer_id = getStripeCustomerIdByEmail($email);
if (!$customer_id) {
  echo "エラー：Stripeの顧客IDが見つかりません。";
  exit;
}

try {
  // ✅ Billingポータルセッション作成
  $session = \Stripe\BillingPortal\Session::create([
    'customer' => $customer_id,
    'return_url' => 'https://udatsu-voyager.com/mypage/membership.php',
  ]);

  // ✅ リダイレクト
  header("Location: " . $session->url);
  exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
  echo "Stripeエラー: " . htmlspecialchars($e->getMessage());
  exit;
}