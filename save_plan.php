<?php
session_start();
header('Content-Type: application/json');

$json = file_get_contents("php://input");
$data = json_decode($json, true);

// 必要データの抽出とバリデーション
$email = $data['email'] ?? '';
$plan  = $data['plan'] ?? 'guest';
$name  = $data['name'] ?? 'ゲストさん';
$uid   = $data['uid']  ?? '';

if (!$uid || !$plan) {
  echo json_encode(['status' => 'error', 'message' => '不正なデータ']);
  exit;
}

// 🔐 セッションに保存
$_SESSION['uid']        = $uid;
$_SESSION['user_plan']  = $plan;
$_SESSION['user_name']  = $name;
$_SESSION['initiated']  = 1;

// ✅ ここから追記：プロフィールファイルに最低限の情報を保存
$userDir = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);

$profileFile = $userDir . $uid . '_profile.json';
$profile = file_exists($profileFile) ? json_decode(file_get_contents($profileFile), true) : [];

if (empty($profile['display_name'])) $profile['display_name'] = $name;
if (empty($profile['email']))        $profile['email']        = $email;

file_put_contents($profileFile, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

session_write_close();

echo json_encode(['status' => 'ok']);