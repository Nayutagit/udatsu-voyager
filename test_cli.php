<?php
// test_cli.php
if (($_GET['key'] ?? '') !== 'udatsu2026test') {
    exit('Forbidden');
}

echo "<pre>";
echo "Current PHP web version: " . phpversion() . "\n\n";

$binaries = [
    '/usr/bin/php',
    '/usr/bin/php8.3',
    '/usr/bin/php8.2',
    '/usr/bin/php8.1',
    '/usr/bin/php8.0',
    '/usr/bin/php7.4',
    '/usr/local/bin/php',
];

foreach ($binaries as $bin) {
    if (file_exists($bin)) {
        echo "Found: $bin\n";
        $out = [];
        exec("$bin -v 2>&1", $out);
        echo "  Version: " . ($out[0] ?? 'Unknown') . "\n";
    } else {
        echo "Not found: $bin\n";
    }
}

echo "\n=== Testing Firebase Buckets ===\n";
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/FirebaseService.php';
use Kreait\Firebase\Factory;

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
        $exists = $bucket->exists();
        echo "  Bucket exists() call: " . ($exists ? "YES" : "NO") . "\n";
        
        // Try uploading a small test string
        $object = $bucket->upload("Hello Firebase from test_cli", ['name' => 'test_connection.txt']);
        echo "  Successfully uploaded test_connection.txt to $bName\n";
        
        $signedUrl = $object->signedUrl(new \DateTime("+10 minutes"));
        echo "  Signed URL: $signedUrl\n";
    } catch (Exception $e) {
        echo "  ERROR on $bName: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
