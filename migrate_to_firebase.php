<?php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/FirebaseService.php';

// ONLY RUNNABLE BY ADMIN
if ($userPlan !== 'admin') {
    die("Permission denied.");
}

$firebase = new FirebaseService();
$bucket = $firebase->getBucket();

$userDir = __DIR__ . '/users/';
$files = glob($userDir . '*_posts.json');

echo "<h1>Firebase Migration Script</h1>";

foreach ($files as $file) {
    if (preg_match('/([a-zA-Z0-9_]+)_posts\.json$/', basename($file), $matches)) {
        $uid = $matches[1];
        
        $posts = json_decode(file_get_contents($file), true);
        if (!$posts) continue;

        echo "<h2>Migrating User: {$uid}</h2><ul>";
        foreach ($posts as $post) {
            $postId = $post['id'] ?? 'job_' . time() . '_' . rand(1000, 9999);
            
            // 1. Upload audio if it exists locally
            $localAudio = $post['audio_file'] ?? '';
            if ($localAudio && strpos($localAudio, 'uploads/') !== false && file_exists(__DIR__ . '/' . $localAudio)) {
                $storagePath = "audio/{$uid}/" . basename($localAudio);
                
                try {
                    // Check if object exists to skip re-uploading
                    $object = $bucket->object($storagePath);
                    if (!$object->exists()) {
                        $bucket->upload(
                            fopen(__DIR__ . '/' . $localAudio, 'r'),
                            ['name' => $storagePath]
                        );
                        echo "<li>Uploaded {$localAudio} to Firebase Storage</li>";
                    }
                    $post['audio_file'] = $storagePath;
                } catch (Exception $e) {
                    echo "<li><span style='color:red'>Failed to upload {$localAudio}: " . $e->getMessage() . "</span></li>";
                }
            }
            
            // 2. Save post to Firestore (use original JSON index handling logic, or just push with ID)
            $firebase->savePost($uid, $postId, $post);
            echo "<li>Migrated post: {$post['title']}</li>";
        }
        echo "</ul>";
        
        // Migrate Dictionary
        $dictFile = $userDir . $uid . '_dictionary.json';
        if (file_exists($dictFile)) {
            $dict = json_decode(file_get_contents($dictFile), true);
            if ($dict) {
                $firebase->getFirestore()->collection('users')->document($uid)->set(['dictionary' => $dict], ['merge' => true]);
                echo "<p>Migrated dictionary.</p>";
            }
        }
    }
}
echo "<h3>Migration Complete!</h3>";
