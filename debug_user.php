<?php
$SECRET = 'udatsu_restore_2026';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); die('Unauthorized'); }

$userDir = __DIR__ . '/users/';
$dirs = scandir($userDir);

echo "<pre>\n";
echo "=== ALL USERS ON SERVER ===\n\n";

$uids = [];
foreach ($dirs as $f) {
    if (preg_match('/^([a-zA-Z0-9_\-]+)_profile\.json$/', $f, $m)) {
        $uids[] = $m[1];
    }
}
// Also find UIDs from posts files without profiles
foreach ($dirs as $f) {
    if (preg_match('/^([a-zA-Z0-9_\-]+)_posts\.json$/', $f, $m)) {
        if (!in_array($m[1], $uids)) $uids[] = $m[1];
    }
}

foreach ($uids as $uid) {
    $pf = $userDir . $uid . '_profile.json';
    $pj = $userDir . $uid . '_posts.json';
    $profile = file_exists($pf) ? json_decode(file_get_contents($pf), true) : [];
    $posts = file_exists($pj) ? json_decode(file_get_contents($pj), true) : [];
    $email = $profile['email'] ?? '';
    $name  = $profile['display_name'] ?? '(no name)';
    $cnt   = count($posts ?? []);
    echo "$uid | $name | $email | posts:$cnt\n";
}
echo "</pre>";
