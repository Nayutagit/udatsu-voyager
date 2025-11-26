<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Stripe SDK を読み込み

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session;

// 🔹 セッション開始 & 検証
session_start();
header('Content-Type: application/json');

$uid   = $_SESSION['uid']        ?? '';
$email = $_SESSION['uid']        ?? ''; // UID=Email前提ならこれでOK
$name  = $_SESSION['user_name']  ?? '';
$plan  = $_GET['plan']           ?? '';
$price_id = $_GET['price_id']   ?? '';

if (!$uid || !$email || !$name || !$plan || !$price_id) {
  http_response_code(400);
  echo json_encode(['error' => 'セッションまたはパラメータが不足しています']);
  exit;
}

// 🔹 Stripe APIキー（LIVE用に差し替える）
Stripe::setApiKey('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'); // ← ご自身のLIVEキーに

// 🔹 顧客作成または取得（必要に応じて実装）
try {
  $customer = Customer::create([
    'email' => $email,
    'name'  => $name,
    'metadata' => ['uid' => $uid]
  ]);
} catch (Exception $e) {
  echo json_encode(['error' => '顧客作成に失敗しました: ' . $e->getMessage()]);
  exit;
}

// 🔹 翌月1日を billing_cycle_anchor に設定
$now = new DateTime();
$next_month = (clone $now)->modify('first day of next month')->setTime(0, 0, 0);
$billing_anchor_timestamp = $next_month->getTimestamp();

try {
  // 🔹 Checkout セッションの作成
  $checkout_session = Session::create([
    'payment_method_types' => ['card'],
    'mode' => 'subscription',
    'customer' => $customer->id,
    'line_items' => [[
      'price' => $price_id,
      'quantity' => 1,
    ]],
    'subscription_data' => [
      'billing_cycle_anchor' => $billing_anchor_timestamp,
      'proration_behavior' => 'create_prorations',
      'metadata' => [
        'uid' => $uid,
        'plan' => $plan,
      ],
    ],
    'success_url' => 'https://udatsuageteko.com/success?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'https://udatsuageteko.com/cancel',
  ]);
} catch (Exception $e) {
  echo json_encode(['error' => 'Checkout セッションの作成に失敗しました: ' . $e->getMessage()]);
  exit;
}

// 🔹 セッションIDを返却
echo json_encode(['sessionId' => $checkout_session->id]);
exit;