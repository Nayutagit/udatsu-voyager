<?php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/FirebaseService.php';

try {
    $firebase = new FirebaseService();
    $storagePath = "audio/YvD0Ab05RJZxSeNEAqAgurEzMEh2/test.txt";
    // just test signed url generation
    $url = $firebase->getSignedUrl($storagePath, 60);
    echo "SUCCESS: " . htmlspecialchars($url);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
