<?php
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json');

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$action = $_POST['action'] ?? '';
$targetUid = $_POST['target_uid'] ?? '';
$postIndex = isset($_POST['post_index']) ? intval($_POST['post_index']) : -1;

if ($action === 'like' && $targetUid && $postIndex !== -1) {
    $userDir = __DIR__ . '/../users/';
    $postsFile = $userDir . $targetUid . '_posts.json';
    
    if (file_exists($postsFile)) {
        $posts = json_decode(file_get_contents($postsFile), true) ?: [];
        
        if (isset($posts[$postIndex])) {
            if (!isset($posts[$postIndex]['likes'])) {
                $posts[$postIndex]['likes'] = [];
            }
            
            $likeIndex = array_search($uid, $posts[$postIndex]['likes']);
            if ($likeIndex === false) {
                // Like it
                $posts[$postIndex]['likes'][] = $uid;
                $status = 'liked';
            } else {
                // Unlike it
                array_splice($posts[$postIndex]['likes'], $likeIndex, 1);
                $status = 'unliked';
            }
            
            file_put_contents($postsFile, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode([
                'status' => 'ok',
                'interaction' => $status,
                'count' => count($posts[$postIndex]['likes'])
            ]);
            exit();
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
