<?php
/**
 * audio_proxy.php
 * Generates a short-lived signed URL for a Firebase Storage audio file
 * and redirects the browser to it.
 * This keeps the audio secure (only logged-in users can play).
 */
session_start();
require_once __DIR__ . '/core/bootstrap.php';

if (empty($uid)) {
    http_response_code(403);
    exit('Unauthorized');
}

$storagePath = $_GET['path'] ?? '';

    $targetUid = $_GET['target_uid'] ?? '';
    if (!$targetUid && preg_match('/^audio\/([^\/]+)\//', $storagePath, $matches)) {
        $targetUid = $matches[1];
    }
    
    // Check if the file belongs to the user
    $isOwner = false;
    if (strpos($storagePath, "audio/{$uid}/") === 0) {
        $isOwner = true;
    } elseif (strpos($storagePath, 'uploads/') === 0) {
        if (strpos($storagePath, $uid) !== false || $targetUid === $uid) {
            $isOwner = true;
        }
    }
    
    if (!$isOwner) {
    
    $isMutual = false;
    if ($targetUid) {
        $userDir = __DIR__ . '/users/';
        $myFollowingFile = $userDir . $uid . '_following.json';
        $myFollowersFile = $userDir . $uid . '_followers.json';
        
        $myFollowing = file_exists($myFollowingFile) ? json_decode(file_get_contents($myFollowingFile), true) ?: [] : [];
        $myFollowers = file_exists($myFollowersFile) ? json_decode(file_get_contents($myFollowersFile), true) ?: [] : [];
        
        if (in_array($targetUid, $myFollowing) && in_array($targetUid, $myFollowers)) {
            $isMutual = true;
        }
    }
    
    if (!$isMutual) {
        http_response_code(403);
        exit('Forbidden: invalid audio path or no mutual follow relationship');
    }
}

// Serve local fallback if needed
if (strpos($storagePath, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $storagePath)) {
    header("Location: /" . $storagePath, true, 302);
    exit();
}

try {
    require_once __DIR__ . '/core/FirebaseService.php';
    $firebase = new FirebaseService();
    $signedUrl = $firebase->getSignedUrl($storagePath, 60); // 60 minute signed URL
    header("Location: {$signedUrl}", true, 302);
} catch (Exception $e) {
    http_response_code(500);
    exit('Audio unavailable: ' . $e->getMessage());
}
