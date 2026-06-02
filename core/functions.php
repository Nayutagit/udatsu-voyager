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

/**
 * ユーザーの投稿一覧を取得する。
 * Firestore から取得を試み、失敗した場合は JSON ファイルにフォールバック。
 *
 * @param  string $uid        ユーザーID
 * @param  string $postsFile  JSON ファイルの絶対パス（フォールバック用）
 * @return array              投稿の配列
 */
function getUserPosts(string $uid, string $postsFile): array {
    try {
        $firestore = new FirestoreClient();
        $col = $firestore->collection('users')->document($uid)->collection('posts');
        $docs = $col->orderBy('created_at', 'DESC')->documents();
        $posts = [];
        foreach ($docs as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                // Firestore の Timestamp を文字列に変換
                foreach ($data as $k => $v) {
                    if ($v instanceof \Google\Cloud\Core\Timestamp) {
                        $data[$k] = $v->get()->format('Y-m-d H:i:s');
                    }
                }
                $posts[] = $data;
            }
        }
        if (!empty($posts)) {
            return $posts;
        }
        // Firestore が空の場合は JSON にフォールバック
    } catch (Throwable $e) {
        // Firestore 取得失敗 → JSON にフォールバック
    }

    // JSON フォールバック
    if (file_exists($postsFile)) {
        $data = json_decode(file_get_contents($postsFile), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

/**
 * ユーザーの投稿一覧を保存する。
 * JSON ファイルへの書き込みを先に行い（必ず成功させる）、
 * その後 Firestore へのデュアルライトを試みる（失敗しても継続）。
 *
 * Firestore 設計（音声/テキスト分離）:
 *   users/{uid}/posts/{postId}/           ← メタデータ（一覧表示用・高速）
 *   users/{uid}/posts/{postId}/content/main ← 本文テキスト（詳細ページ用）
 *
 * @param  string $uid        ユーザーID
 * @param  string $postsFile  JSON ファイルの絶対パス
 * @param  array  $posts      投稿の配列
 * @return void
 */
function saveUserPosts(string $uid, string $postsFile, array $posts): void {
    // 1. まず JSON に書き込む（従来通り。これは必ず成功させる）
    file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // 2. Firestore にデュアルライト（失敗しても JSON は守られている）
    try {
        $firestore = new FirestoreClient();
        $postsCol  = $firestore->collection('users')->document($uid)->collection('posts');
        $now       = new \Google\Cloud\Core\Timestamp(new \DateTime());

        foreach ($posts as $post) {
            $postId = $post['id'] ?? null;
            if (empty($postId)) continue;

            // --- メタデータのみ（本文テキストは除外）---
            $TEXT_FIELDS = ['text', 'original_text', 'refined_text', 'content'];
            $meta = [];
            foreach ($post as $k => $v) {
                if (!in_array($k, $TEXT_FIELDS) && $v !== null && $v !== '') {
                    $meta[$k] = $v;
                }
            }
            $meta['updated_at'] = $now;
            if (!isset($meta['created_at'])) {
                $meta['created_at'] = $now;
            }

            $postRef = $postsCol->document($postId);

            // メタデータを保存
            $postRef->set($meta, ['merge' => true]);

            // --- テキスト本文（サブコレクション content/main に分離保存）---
            $textData = [];
            foreach ($TEXT_FIELDS as $f) {
                if (!empty($post[$f])) $textData[$f] = $post[$f];
            }
            if (!empty($textData)) {
                $textData['updated_at'] = $now;
                $postRef->collection('content')->document('main')->set($textData, ['merge' => true]);
            }
        }
    } catch (Throwable $e) {
        // Firestore 書き込み失敗はログだけ残して継続（JSON は守られている）
        $logFile = dirname($postsFile, 2) . '/log/analysis_log.txt';
        if (file_exists(dirname($logFile))) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - [Firestore write error] uid={$uid}: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
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