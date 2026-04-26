<?php
require_once __DIR__ . '/../core/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$targetUid = $input['targetUid'] ?? '';

if (empty($action) || empty($targetUid)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

if ($targetUid === $uid) {
    echo json_encode(['status' => 'error', 'message' => 'You cannot follow yourself']);
    exit;
}

$userDir = __DIR__ . '/../users/';

function getList($filePath) {
    if (!file_exists($filePath)) return [];
    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

function saveList($filePath, $list) {
    file_put_contents($filePath, json_encode(array_values(array_unique($list))));
}

$myFollowingFile = $userDir . $uid . '_following.json';
$targetFollowersFile = $userDir . $targetUid . '_followers.json';

$myFollowing = getList($myFollowingFile);
$targetFollowers = getList($targetFollowersFile);

if ($action === 'follow') {
    if (!in_array($targetUid, $myFollowing)) {
        $myFollowing[] = $targetUid;
    }
    if (!in_array($uid, $targetFollowers)) {
        $targetFollowers[] = $uid;
    }
} elseif ($action === 'unfollow') {
    $myFollowing = array_filter($myFollowing, fn($id) => $id !== $targetUid);
    $targetFollowers = array_filter($targetFollowers, fn($id) => $id !== $uid);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

saveList($myFollowingFile, $myFollowing);
saveList($targetFollowersFile, $targetFollowers);

echo json_encode(['status' => 'success']);
