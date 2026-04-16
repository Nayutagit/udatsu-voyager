<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;

class FirebaseService {
    private $factory;
    private $firestore;
    private $storageBucket;
    private $bucketName = 'udatsu-ageteko.appspot.com';

    public function __construct() {
        $credentialsPath = __DIR__ . '/../config/firebase_credentials.json';
        if (!file_exists($credentialsPath)) {
            throw new Exception("Firebase credentials not found.");
        }

        $this->factory = (new Factory)
            ->withServiceAccount($credentialsPath);
    }

    public function getFirestore() {
        if (!$this->firestore) {
            $this->firestore = $this->factory->createFirestore()->database();
        }
        return $this->firestore;
    }

    public function getBucket() {
        if (!$this->storageBucket) {
            $this->storageBucket = $this->factory->createStorage()->getBucket($this->bucketName);
        }
        return $this->storageBucket;
    }

    /**
     * Store/Update a post in Firestore
     */
    public function savePost($uid, $postId, $postData) {
        $db = $this->getFirestore();
        if (!isset($postData['created_at'])) {
            $postData['created_at'] = time();
        }
        $db->collection('users')->document($uid)->collection('posts')->document($postId)->set($postData);
    }

    /**
     * Get a single post
     */
    public function getPost($uid, $postId) {
        $db = $this->getFirestore();
        $doc = $db->collection('users')->document($uid)->collection('posts')->document($postId)->snapshot();
        return $doc->exists() ? $doc->data() : null;
    }

    /**
     * Get all posts for a user, sorted descending by created_at
     */
    public function getAllPosts($uid) {
        $db = $this->getFirestore();
        $query = $db->collection('users')->document($uid)->collection('posts')->orderBy('created_at', 'DESC');
        $docs = $query->documents();
        $posts = [];
        foreach ($docs as $doc) {
            $posts[] = $doc->data();
        }
        return $posts;
    }

    /**
     * Delete a post
     */
    public function deletePost($uid, $postId) {
        $this->getFirestore()->collection('users')->document($uid)->collection('posts')->document($postId)->delete();
    }
}
