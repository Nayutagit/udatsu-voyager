<?php
/**
 * migrate_audio_to_firebase.php
 * One-time script: Upload existing local audio files to Firebase Storage
 * and update the JSON posts file with the new storage paths.
 * 
 * Run ONCE at: https://udatsu-voyager.com/migrate_audio_to_firebase.php
 */
session_start();
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/FirebaseService.php';

if ($userPlan !== 'admin') {
    die("Permission denied.");
}

$firebase = new FirebaseService();
$userDir  = __DIR__ . '/users/';
$files    = glob($userDir . '*_posts.json');

echo "<h1>🔥 Firebase Audio Migration</h1><pre>";

foreach ($files as $jsonFile) {
    if (!preg_match('/([a-zA-Z0-9_]+)_posts\.json$/', basename($jsonFile), $m)) continue;
    $fileUid = $m[1];

    $posts   = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($posts)) continue;

    $changed = false;
    echo "\n[User: {$fileUid}]\n";

    foreach ($posts as &$post) {
        $localPath = $post['audio_file'] ?? '';
        if (empty($localPath)) continue;

        // Already migrated (Firebase path starts with "audio/")
        if (strpos($localPath, 'audio/') === 0) {
            echo "  ✅ Already in Firebase: {$localPath}\n";
            continue;
        }

        $absPath = __DIR__ . '/' . $localPath;
        if (!file_exists($absPath)) {
            echo "  ⚠️  File not found, skipping: {$localPath}\n";
            continue;
        }

        try {
            $storagePath = $firebase->uploadAudio($absPath, $fileUid);
            $post['audio_file'] = $storagePath;
            $changed = true;
            echo "  ⬆️  Uploaded: {$localPath} → {$storagePath}\n";
            @unlink($absPath); // Remove local file after upload
        } catch (Exception $e) {
            echo "  ❌ Failed: {$localPath} — " . $e->getMessage() . "\n";
        }
    }
    unset($post);

    if ($changed) {
        file_put_contents($jsonFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  💾 JSON updated.\n";
    }
}

echo "\n✅ Migration complete!</pre>";
