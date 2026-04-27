<?php
/**
 * share_upload.php
 * PWA Share Target handler - receives audio files shared from iOS Share Sheet.
 * Processes the file the same way as voyager_upload.php.
 */
require_once __DIR__ . '/core/bootstrap.php';

// ログインしていない場合はログインページへ
if (empty($uid) || $userPlan === 'guest') {
    header('Location: index.php?shared=1');
    exit();
}

require_once __DIR__ . '/plan_limits.php';
$limits      = $plan_limits[$userPlan] ?? $plan_limits['trial'];
$maxSizeMB   = $limits['max_size_mb'] ?? 1000;
$maxSizeBytes = $maxSizeMB * 1024 * 1024;
$uploadLimit = $limits['daily_uses'];

$userDir  = __DIR__ . '/users/';
if (!file_exists($userDir)) mkdir($userDir, 0755, true);
$userFile = $userDir . $uid . '_upload.json';
$today    = date('Y-m-d');

$data        = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];
$uploadCount = $data[$today] ?? 0;
$limitReached = ($userPlan !== 'admin') && ($uploadCount >= $uploadLimit);

if ($limitReached) {
    header('Location: mypage/mypage.php?error=limit');
    exit();
}

$fileKey = $_FILES['audioFile'] ?? null;

if (!$fileKey || !is_uploaded_file($fileKey['tmp_name'])) {
    // No file received => just go to upload page
    header('Location: voyager_upload.php');
    exit();
}

if ($fileKey['size'] > $maxSizeBytes) {
    header("Location: error_file_size.php?plan={$userPlan}&limit={$maxSizeMB}");
    exit();
}

$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

$originalName = pathinfo($fileKey['name'], PATHINFO_FILENAME);
$extension    = pathinfo($fileKey['name'], PATHINFO_EXTENSION);
$safeName     = preg_replace("/[^a-zA-Z0-9]/", "_", $originalName);
$filename     = $safeName . "_" . time() . "." . $extension;
$targetPath   = $uploadDir . $filename;

$finfo     = new finfo(FILEINFO_MIME_TYPE);
$mimeType  = $finfo->file($fileKey['tmp_name']);
$allowedTypes = [
    'audio/m4a','audio/mp4','audio/x-m4a','audio/mpeg','audio/mp3',
    'audio/x-mp3','audio/wav','audio/x-wav','audio/webm','video/webm',
    'audio/ogg','video/mp4','audio/aac','audio/x-aac','audio/mp4a-latm'
];

if (!in_array($mimeType, $allowedTypes, true)) {
    header('Location: voyager_upload.php?error=format');
    exit();
}

if (move_uploaded_file($fileKey['tmp_name'], $targetPath)) {
    // Count upload
    if ($userPlan !== 'admin') {
        $data[$today] = $uploadCount + 1;
        file_put_contents($userFile, json_encode($data));
    }

    // Pass to analyze pipeline via session
    $_SESSION['audio_path']    = $targetPath;
    $_SESSION['original_name'] = $originalName;

    // Trigger analyze (redirect to processing page for smooth UX)
    header('Location: processing.php');
    exit();
}

header('Location: voyager_upload.php?error=save');
exit();
