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
if (!$targetUid) {
    $targetUid = $uid;
}

// Expand raw filenames to the user's specific uploads directory
if ($storagePath && strpos($storagePath, '/') === false && $targetUid) {
    $storagePath = 'uploads/' . $targetUid . '/' . $storagePath;
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
    
    if (!$isOwner && $userPlan !== 'admin') {
    
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

$download = isset($_GET['download']) && $_GET['download'] === '1';

// Serve local fallback if needed
if (strpos($storagePath, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $storagePath)) {
    if ($download) {
        $filename = basename($storagePath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize(__DIR__ . '/' . $storagePath));
        readfile(__DIR__ . '/' . $storagePath);
        exit();
    }
    header("Location: /" . $storagePath, true, 302);
    exit();
}

try {
    require_once __DIR__ . '/core/FirebaseService.php';
    $firebase = new FirebaseService();
    
    $options = [];
    if ($download) {
        $filename = basename($storagePath);
        $options['queryParams'] = [
            'responseContentDisposition' => 'attachment; filename="' . rawurlencode($filename) . '"'
        ];
    }
    
    $signedUrl = $firebase->getSignedUrl($storagePath, 60, $options); // 60 minute signed URL
    header("Location: {$signedUrl}", true, 302);
} catch (Exception $e) {
    http_response_code(500);
    exit('Audio unavailable: ' . $e->getMessage());
}
