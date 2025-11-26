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