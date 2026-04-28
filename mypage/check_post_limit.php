<?php
session_start();

$uid = $_SESSION['uid'] ?? '';
$plan = $_SESSION['user_plan'] ?? 'trial';

if (!$uid) {
  http_response_code(401);
  echo json_encode(["status" => "error", "message" => "ログイン情報がありません"]);
  exit;
}

require_once __DIR__ . '/../plan_limits.php';

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$limit = $planLimits[$plan]['post_limit'] ?? 0;
$currentMonth = date('Y-m');

$validPosts = array_filter($posts, function ($post) use ($currentMonth) {
  $countableStatuses = ['Inbox', '申請中', '公開済', 'My Udastack追加済'];
  return in_array($post['status'] ?? '', $countableStatuses, true)
      && (!empty($post['date']) && strpos($post['date'], $currentMonth) === 0);
});


$currentCount = count($validPosts);

header('Content-Type: application/json; charset=UTF-8');

if ($limit > 0 && $currentCount >= $limit) {
  echo json_encode([
    "status" => "limit",
    "message" => "このプランでは今月の投稿保存上限（{$limit}件）に達しています。"
  ]);
} else {
  echo json_encode([
    "status" => "ok",
    "remaining" => $limit > 0 ? $limit - $currentCount : '無制限'
  ]);
}