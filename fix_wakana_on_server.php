<?php
$file = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';
if (!file_exists($file)) {
    die("File not found on server.");
}
$data = json_decode(file_get_contents($file), true);
$updated = 0;
if (is_array($data)) {
    foreach ($data as &$post) {
        if (isset($post['status']) && $post['status'] === 'My Udastack追加済') {
            $post['is_shared'] = 1;
            $updated++;
        }
    }
}
if ($updated > 0) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "Successfully updated $updated posts for Wakana on the production server.";
} else {
    echo "No posts needed updating (or already updated).";
}
// Optionally delete this file after it's run
@unlink(__FILE__);
