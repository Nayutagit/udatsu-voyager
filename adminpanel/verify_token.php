<?php
session_start();
require_once __DIR__ . '/../../firebase/firebase_admin.php';

header('Content-Type: application/json');

// JSONとして受け取る
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$token = $data['token'] ?? '';

if (!$token) {
  echo json_encode(['success' => false, 'error' => 'トークンがありません']);
  exit;
}

try {
  $verifiedIdToken = $auth->verifyIdToken($token);
  $email = $verifiedIdToken->claims()->get('email');

  if ($email === 'udatsuageteko@gmail.com') {
    $_SESSION['is_admin_authenticated'] = true;
    echo json_encode(['success' => true]); // ✅ JSON形式で成功返す
  } else {
    echo json_encode(['success' => false, 'error' => '許可されていないメールアドレスです', 'email' => $email]);
  }

} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => 'トークンの検証に失敗しました', 'message' => $e->getMessage()]);
}