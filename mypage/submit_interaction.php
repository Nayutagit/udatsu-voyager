<?php
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json');

if (empty($uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$action = $_POST['action'] ?? '';
$targetUid = $_POST['target_uid'] ?? '';
$postId = $_POST['id'] ?? '';
$postIndex = isset($_POST['post_index']) ? intval($_POST['post_index']) : -1;

if (empty($targetUid)) {
    $targetUid = $uid; // Default to self
}

$userDir = __DIR__ . '/../users/';
$postsFile = $userDir . $targetUid . '_posts.json';
$posts = [];

if (file_exists($postsFile)) {
    $posts = json_decode(file_get_contents($postsFile), true) ?: [];
}

// Find index if post ID is provided
if ($postId && $postIndex === -1) {
    foreach ($posts as $idx => $p) {
        if (($p['id'] ?? '') === $postId) {
            $postIndex = $idx;
            break;
        }
    }
}

// Ensure the post index is valid
if ($postIndex !== -1 && isset($posts[$postIndex])) {
    $realPostId = $posts[$postIndex]['id'] ?? $postId;

    if ($action === 'like' || $action === 'unlike') {
        if (!isset($posts[$postIndex]['likes'])) {
            $posts[$postIndex]['likes'] = [];
        }
        
        $likeIndex = array_search($uid, $posts[$postIndex]['likes']);
        
        if ($action === 'like') {
            if ($likeIndex === false) {
                $posts[$postIndex]['likes'][] = $uid;
            }
            $status = 'liked';
        } else {
            if ($likeIndex !== false) {
                array_splice($posts[$postIndex]['likes'], $likeIndex, 1);
            }
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

    if ($action === 'save' || $action === 'unsave') {
        $savedFile = $userDir . $uid . '_saved.json';
        $savedItems = file_exists($savedFile) ? (json_decode(file_get_contents($savedFile), true) ?: []) : [];

        if ($action === 'save') {
            $exists = false;
            foreach ($savedItems as $item) {
                if (($item['post_id'] ?? '') === $realPostId && ($item['author_uid'] ?? '') === $targetUid) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $savedItems[] = [
                    'author_uid' => $targetUid,
                    'post_id' => $realPostId,
                    'saved_at' => date('Y-m-d H:i:s')
                ];
            }
            $status = 'saved';
        } else {
            $savedItems = array_filter($savedItems, function($item) use ($targetUid, $realPostId) {
                return !(($item['post_id'] ?? '') === $realPostId && ($item['author_uid'] ?? '') === $targetUid);
            });
            $savedItems = array_values($savedItems);
            $status = 'unsaved';
        }

        file_put_contents($savedFile, json_encode($savedItems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode([
            'status' => 'ok',
            'interaction' => $status
        ]);
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
