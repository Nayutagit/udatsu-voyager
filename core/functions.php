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

// Keep log files from growing infinitely by truncating them to the last N lines when they exceed a threshold
function rotate_log_file(string $path, int $maxLines = 1000): void {
    if (!file_exists($path)) {
        return;
    }
    // Only read and process if the file is larger than 1MB (to prevent performance overhead)
    if (filesize($path) > 1024 * 1024) {
        $lines = file($path);
        if ($lines !== false && count($lines) > $maxLines) {
            $keep = array_slice($lines, -$maxLines);
            file_put_contents($path, implode("", $keep));
        }
    }
}