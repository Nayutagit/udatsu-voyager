<?php
use Google\Cloud\Firestore\FirestoreClient; // Firebase PHP SDK を想定

// Firestore からプランを取得（uid→plan）。失敗時は null を返す。
function getUserPlanFromFirestore(string $uid): ?string {
  try {
    $firestore = new FirestoreClient();
    $doc = $firestore->collection('users')->document($uid)->snapshot();
    if ($doc->exists() && $doc->offsetExists('plan')) {
      return $doc['plan'];
    }
  } catch (Throwable $e) {
    // ログに残すなど
  }
  return null;
}

// Dynamically discover the best available PHP CLI version (e.g. for XServer compatibility)
function getBestPhpCliPath(): string {
    $candidates = [
        '/usr/bin/php8.3',
        '/usr/bin/php8.2',
        '/usr/bin/php8.1',
        '/usr/bin/php8.0',
        '/usr/bin/php7.4',
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            return $c;
        }
    }
    return 'php';
}