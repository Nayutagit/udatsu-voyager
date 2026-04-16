<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;

class FirebaseService {
    private $factory;
    private $bucket;
    private $bucketName = 'udatsu-ageteko.appspot.com';

    public function __construct() {
        $credentialsPath = __DIR__ . '/../config/firebase_credentials.json';
        if (!file_exists($credentialsPath)) {
            throw new Exception("Firebase credentials not found at: " . $credentialsPath);
        }
        $this->factory = (new Factory)->withServiceAccount($credentialsPath);
    }

    private function getBucket() {
        if (!$this->bucket) {
            $this->bucket = $this->factory->createStorage()->getBucket($this->bucketName);
        }
        return $this->bucket;
    }

    /**
     * Upload an audio file to Firebase Storage.
     * @return string The storage path (e.g. "audio/{uid}/filename.m4a")
     */
    public function uploadAudio(string $localPath, string $uid): string {
        $filename = basename($localPath);
        $storagePath = "audio/{$uid}/{$filename}";

        $bucket = $this->getBucket();
        $bucket->upload(
            fopen($localPath, 'r'),
            ['name' => $storagePath]
        );

        return $storagePath;
    }

    /**
     * Generate a short-lived signed URL (1 week) for a storage path.
     */
    public function getSignedUrl(string $storagePath, int $expiryMinutes = 10080): string {
        $bucket = $this->getBucket();
        $object = $bucket->object($storagePath);

        $signedUrl = $object->signedUrl(new \DateTime("+{$expiryMinutes} minutes"));
        return (string) $signedUrl;
    }

    /**
     * Check if a storage object exists.
     */
    public function audioExists(string $storagePath): bool {
        try {
            return $this->getBucket()->object($storagePath)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}
