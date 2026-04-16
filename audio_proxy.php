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

// Security: ensure the path belongs to this user
if (empty($storagePath) || strpos($storagePath, "audio/{$uid}/") !== 0) {
    // Check if it's a local fallback path (uploads/)
    if (strpos($storagePath, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $storagePath)) {
        // Serve local file directly
        $mime = mime_content_type(__DIR__ . '/' . $storagePath) ?: 'audio/mpeg';
        header("Content-Type: {$mime}");
        header("Content-Length: " . filesize(__DIR__ . '/' . $storagePath));
        header("Accept-Ranges: bytes");
        readfile(__DIR__ . '/' . $storagePath);
        exit();
    }
    http_response_code(403);
    exit('Forbidden: invalid audio path');
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
