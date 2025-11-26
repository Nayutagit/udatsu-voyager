<?php
session_start();

// ✅ ヘッダー指定（JSON）
header('Content-Type: application/json; charset=UTF-8');

// ✅ POST(JSON) から取得
$data = json_decode(file_get_contents('php://input'), true);
$plan = $data['plan'] ?? '';

// ✅ 許可プラン一覧
$allowed = ['light', 'standard', 'premium'];

if (in_array($plan, $allowed, true)) {
    $_SESSION['pending_plan'] = $plan;
    session_write_close();

    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => '無効なプラン指定です'
    ]);
}