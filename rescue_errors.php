<?php
$userDir = __DIR__ . '/users/';
if (!is_dir($userDir)) exit("No users dir\n");

$files = glob($userDir . '*_posts.json');
foreach ($files as $file) {
    $posts = json_decode(file_get_contents($file), true);
    if (!is_array($posts)) continue;
    
    $changed = false;
    foreach ($posts as &$p) {
        if (($p['status'] ?? '') === 'エラー') {
            // エラーを「Inbox（成功）」扱いにする。要約がなければ原文をセット。
            $p['status'] = 'Inbox';
            if (empty($p['text']) && !empty($p['original_text'])) {
                $p['text'] = $p['original_text'];
            }
            if (empty($p['title'])) {
                $p['title'] = "（自動修復中...）";
            }
            $changed = true;
        }
    }
    
    if ($changed) {
        file_put_contents($file, json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "Rescued posts in $file\n";
    }
}
echo "Rescue operation completed.\n";
