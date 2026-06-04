<?php
/**
 * debug_user.php - 一時デバッグスクリプト。使用後削除。
 */
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

session_start();
$uid = $_GET['uid'] ?? ($_SESSION['uid'] ?? '');
if (empty($uid)) die('uid required (?uid=xxx or login first)');

$userDir   = __DIR__ . '/users/';
$postsFile = $userDir . $uid . '_posts.json';

echo "<pre>\n";
echo "=== DEBUG uid: $uid ===\n\n";

// file check
echo "posts file exists: " . (file_exists($postsFile) ? 'YES' : 'NO') . "\n";
echo "posts file size:   " . (file_exists($postsFile) ? filesize($postsFile) . ' bytes' : 'N/A') . "\n";

if (!file_exists($postsFile)) { echo "FILE NOT FOUND\n"; exit; }

$raw = file_get_contents($postsFile);
$posts = json_decode($raw, true);

echo "json_decode result: " . (is_array($posts) ? 'OK' : 'FAILED - json_last_error=' . json_last_error_msg()) . "\n";
echo "post count: " . count($posts ?? []) . "\n";

// check plan_limits
require_once __DIR__ . '/plan_limits.php';
$sessUid  = $_SESSION['uid'] ?? '(not logged in)';
$userPlan = $_SESSION['userPlan'] ?? 'free';
echo "\nSession uid:  $sessUid\n";
echo "Session plan: $userPlan\n";
$postLimit = $plan_limits[$userPlan]['post_limit'] ?? 100;
echo "Post limit for plan: $postLimit\n";

// visible posts
$visiblePosts = array_filter($posts ?? [], fn($p) => ($p['status'] ?? '') !== '削除済');
echo "Visible posts (non-deleted): " . count($visiblePosts) . "\n\n";

// sample
echo "--- First 3 posts ---\n";
$i = 0;
foreach ($posts ?? [] as $p) {
    if ($i++ >= 3) break;
    echo "  id: " . ($p['id'] ?? 'NO_ID') . "\n";
    echo "  title: " . mb_strimwidth($p['title'] ?? '', 0, 40, '...') . "\n";
    echo "  status: " . ($p['status'] ?? '') . "\n\n";
}
echo "</pre>";
