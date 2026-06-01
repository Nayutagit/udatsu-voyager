<?php
// test_firebase.php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/FirebaseService.php';

use Kreait\Firebase\Factory;

if (($_GET['key'] ?? '') !== 'udatsu2026test') {
    exit('Forbidden');
}

echo "<pre>";
echo "Testing Firebase Buckets...\n";

$credentialsPath = __DIR__ . '/config/firebase_credentials.json';
$factory = (new Factory)->withServiceAccount($credentialsPath);
$storage = $factory->createStorage();

$buckets = [
    'udatsu-ageteko.appspot.com',
    'udatsu-ageteko.firebasestorage.app',
];

foreach ($buckets as $bName) {
    try {
        echo "Testing Bucket: $bName\n";
        $bucket = $storage->getBucket($bName);
        
        // Let's check if we can list objects or check exist
        // Note: Listing objects might fail if permissions are not set, but let's see what error it returns.
        $exists = $bucket->exists();
        echo "  Bucket exists() call: " . ($exists ? "YES" : "NO") . "\n";
        
        // Try uploading a small test string
        $object = $bucket->upload("Hello Firebase", ['name' => 'test_connection.txt']);
        echo "  Successfully uploaded test_connection.txt to $bName\n";
        
        $signedUrl = $object->signedUrl(new \DateTime("+10 minutes"));
        echo "  Signed URL: $signedUrl\n";
        
    } catch (Exception $e) {
        echo "  ERROR on $bName: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";
