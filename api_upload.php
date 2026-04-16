<?php
/**
 * api_upload.php
 * Token-authenticated audio upload endpoint for Apple Shortcuts.
 * Usage: POST https://udatsu-voyager.com/api_upload.php?token=YOUR_TOKEN
 * with multipart file field: audioFile
 */

// No session needed – token-based auth
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/plan_limits.php';

header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'トークンがありません。']);
    exit();
}

// Find which user owns this token
$userDir = __DIR__ . '/users/';
$uid = null;
$userPlan = 'trial';

foreach (glob($userDir . '*_profile.json') as $profileFile) {
    $data = json_decode(file_get_contents($profileFile), true);
    if (($data['api_token'] ?? '') === $token) {
        // Extract uid from filename
        $uid = str_replace(['_profile.json', $userDir], '', $profileFile);
        $uid = basename(str_replace('_profile.json', '', $profileFile));
        $userPlan = $data['plan'] ?? 'trial';
        break;
    }
}

if (!$uid) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '無効なトークンです。マイページから再発行してください。']);
    exit();
}

// Check daily limit
$limits      = $plan_limits[$userPlan] ?? $plan_limits['trial'];
$uploadLimit = $limits['daily_uses'];
$maxSizeMB   = $limits['max_size_mb'] ?? 1500;
$maxSizeBytes = $maxSizeMB * 1024 * 1024;

$userFile = $userDir . $uid . '_upload.json';
$today    = date('Y-m-d');
$data     = file_exists($userFile) ? json_decode(file_get_contents($userFile), true) : [];
$uploadCount = $data[$today] ?? 0;

if ($userPlan !== 'admin' && $uploadCount >= $uploadLimit) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => "本日のアップロード上限（{$uploadLimit}回）に達しています。"]);
    exit();
}

// Get uploaded file
$fileKey = $_FILES['audioFile'] ?? null;
if (!$fileKey || !is_uploaded_file($fileKey['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '音声ファイルが見つかりません。フィールド名は "audioFile" にしてください。']);
    exit();
}

if ($fileKey['size'] > $maxSizeBytes) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => "ファイルサイズが上限（{$maxSizeMB}MB）を超えています。"]);
    exit();
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($fileKey['tmp_name']);
$allowedTypes = [
    'audio/m4a','audio/mp4','audio/x-m4a','audio/mpeg','audio/mp3',
    'audio/x-mp3','audio/wav','audio/x-wav','audio/webm','video/webm',
    'audio/ogg','video/mp4','audio/aac','audio/x-aac','audio/mp4a-latm'
];
if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(415);
    echo json_encode(['status' => 'error', 'message' => "対応していないファイル形式です: {$mimeType}"]);
    exit();
}

// Save to uploads/
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
$safeName  = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($fileKey['name'], PATHINFO_FILENAME));
$extension = pathinfo($fileKey['name'], PATHINFO_EXTENSION) ?: 'mp4';
$filename  = $safeName . '_' . time() . '.' . $extension;
$targetPath = 'uploads/' . $filename;

if (!move_uploaded_file($fileKey['tmp_name'], __DIR__ . '/' . $targetPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'ファイルの保存に失敗しました。']);
    exit();
}

// Increment usage count
if ($userPlan !== 'admin') {
    $data[$today] = $uploadCount + 1;
    file_put_contents($userFile, json_encode($data));
}

// Start analysis in background (same as analyze.php flow)
// We call analyze.php via exec() since there's no browser session here
$analyzeScript = __DIR__ . '/run_analysis.php';
$cmd = "php " . escapeshellarg($analyzeScript)
    . " " . escapeshellarg($uid)
    . " " . escapeshellarg($targetPath)
    . " > /dev/null 2>&1 &";
exec($cmd);

echo json_encode([
    'status'  => 'ok',
    'message' => '音声を受信しました。バックグラウンドで解析を開始しています。数分後にダッシュボードをご確認ください 🎙',
    'file'    => basename($targetPath)
]);
exit();
