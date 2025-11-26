<?php
// Udatsu Stripe Webhook通知用スクリプト

$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

// Slack通知Webhook（ナユタ専用）
$slackWebhook = 'https://hooks.slack.com/services/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';

// Stripeの決済成功イベント
if ($event['type'] === 'invoice.payment_succeeded') {
    $data = $event['data']['object'];

    $email = $data['customer_email'] ?? '（メール不明）';
    $amount = isset($data['amount_paid']) ? number_format($data['amount_paid'] / 100) : '金額不明';
    $plan = $data['lines']['data'][0]['price']['nickname'] ?? 'プラン名不明';

    // Slack用メッセージ本文
    $text = "🎉 *Udatsu有料プラン購入検知！*\n"
          . "📧 `{$email}`\n"
          . "💴 ¥{$amount}\n"
          . "📦 プラン: {$plan}";

    // Slackに通知送信
    $payload = json_encode(['text' => $text]);

    $ch = curl_init($slackWebhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}

// 応答（Stripeに200返さないとエラー扱いになる）
http_response_code(200);
echo 'ok';
?>