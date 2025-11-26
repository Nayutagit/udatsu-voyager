<?php
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

// 🔹 POSTデータを取得（JSON）
$input = json_decode(file_get_contents('php://input'), true);
$uid   = isset($input['uid']) ? trim((string)$input['uid']) : '';
$email = isset($input['email']) ? trim((string)$input['email']) : '';
$name  = isset($input['name']) ? trim((string)$input['name']) : '';

// 🔹 入力チェック
if (!$uid || !$email || !$name) {
  echo json_encode(['status' => 'error', 'message' => '必要な情報が不足しています']);
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

// ✅ 成功レスポンス
echo json_encode(['status' => 'ok']);
exit;