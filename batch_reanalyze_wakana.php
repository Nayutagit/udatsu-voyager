<?php
/**
 * batch_reanalyze_wakana.php
 * 和佳奈さんの投稿を1件ずつ順番に再解析する（API制限回避のため順次実行）
 */
$SECRET = 'udatsu_restore_2026';
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403); die('Unauthorized');
}

$root = __DIR__;
require_once $root . '/core/bootstrap.php';
$targetUid = '3xRUBOkESVQShNx4XWSpsO0w5w13';
$postsFile = $root . '/users/' . $targetUid . '_posts.json';

if (!file_exists($postsFile)) die("posts.json not found\n");

$posts = json_decode(file_get_contents($postsFile), true) ?? [];
$targetPosts = array_filter($posts, fn($p) => ($p['status'] ?? '') === '解析待ち');

echo "解析待ちの投稿数: " . count($targetPosts) . "\n";

$phpPath = getBestPhpCliPath();
$logFile = $root . '/log/batch_wakana.log';

foreach ($targetPosts as $idx => $post) {
    $audioPath = $post['audio_file'] ?? '';
    $jobId = $post['id'];
    $title = $post['title'];
    
    if (!$audioPath) continue;
    
    echo "[" . date('H:i:s') . "] 開始: $title ($jobId)\n";
    
    // ステータスを更新して保存
    $posts = json_decode(file_get_contents($postsFile), true) ?? [];
    foreach ($posts as $k => $p) {
        if ($p['id'] === $jobId) {
            $posts[$k]['status'] = '解析中';
            break;
        }
    }
    file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // run_analysis.php を同期実行（シェル経由）
    $cmd = escapeshellarg($phpPath) . ' '
         . escapeshellarg("{$root}/run_analysis.php") . ' '
         . escapeshellarg($targetUid) . ' '
         . escapeshellarg($audioPath) . ' '
         . escapeshellarg($jobId)
         . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
         
    exec($cmd);
    
    echo "[" . date('H:i:s') . "] 完了: $title\n";
    
    // Gemini APIのレートリミット対策（15リクエスト/分 = 4秒間隔。少し余裕を持って5秒待機）
    sleep(5);
}

echo "全件の再解析リクエストが完了しました。\n";
