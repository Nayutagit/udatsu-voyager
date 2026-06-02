<?php
require_once __DIR__ . '/../../firebase/firestore.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\Webhook;

$endpoint_secret = 'whsec_qdMbin0wHScsL6nHJDexcAkHtPDnNNBd';
$logPath = __DIR__ . '/webhook_log.txt';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

file_put_contents($logPath, "📥 Raw payload:\n{$payload}\n\n", FILE_APPEND);

try {
    $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\Exception $e) {
    file_put_contents($logPath, "❌ 検証失敗: {$e->getMessage()}\n", FILE_APPEND);
    http_response_code(400);
    exit();
}

// 🔄 Stripeの価格ID → Udatsuプラン名のマッピング
$priceToPlan = [
    'price_1ROJF1RoMZhQzJflZGvQHTUB' => 'light',
    'price_1ROJHqRoMZhQzJflC40Kme0d' => 'standard',
    'price_1ROJIVRoMZhQzJfl15JPOqAQ' => 'premium',
];

// 🎯 invoice.paid イベントだけ処理
if ($event->type === 'invoice.paid') {
    $invoice = $event->data->object;

    // JSONを連想配列で再解釈
    $decoded = json_decode($payload, true);
    $invoiceData = $decoded['data']['object'];

    $email = $invoiceData['customer_email'] ?? '';
    $lineItem = $invoiceData['lines']['data'][0] ?? [];
    $priceId = $lineItem['pricing']['price_details']['price'] ?? '';

    file_put_contents($logPath, "📌 Extracted: email={$email}, price_id={$priceId}\n", FILE_APPEND);

    $plan = $priceToPlan[$priceId] ?? null;

    if ($email && $plan) {
        $saved = saveUserPlanToFirestore($email, $plan);
        $log = $saved
            ? "✅ Firestore保存成功: {$email} → {$plan}"
            : "❌ Firestore保存失敗: {$email} → {$plan}";
        file_put_contents($logPath, $log . "\n", FILE_APPEND);
    } else {
        file_put_contents($logPath, "⚠️ 情報不完全: email={$email}, price_id={$priceId}, plan={$plan}\n", FILE_APPEND);
    }
}

http_response_code(200);