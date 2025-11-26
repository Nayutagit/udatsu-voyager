<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
  header("Location: index.php");
  exit();
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$stacked = array_filter($posts, fn($p) => $p['status'] === 'My Udastack追加済');
$summaryList = array_map(fn($p) => $p['summary'] ?? '', $stacked);
$combinedSummary = implode("\n\n---\n\n", array_filter($summaryList));

// テキスト形式で出力
header("Content-Type: text/plain; charset=UTF-8");
header("Content-Disposition: attachment; filename=udastack_summary.txt");

echo "【Udatsu 思考資産まとめ】\n\n";
echo $combinedSummary;
exit;