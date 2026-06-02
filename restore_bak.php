<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$action = $_GET['action'] ?? 'dump_current';

// The reconstructed bak file
$mainFile  = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';
$bakFile   = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json.bak.reconstructed';

if ($action === 'dump_current') {
    // Dump the current (240-post) file to find what real VJ posts are in it
    // that match the job IDs from the analysis log
    $data = json_decode(file_get_contents($mainFile), true);
    
    // Known good job IDs from log analysis (the 35 audio posts at 11:59)
    $goodJobIds = [
        'job_69fa8d3e8a360', 'job_69f31b9a95a4e', 'job_69fac38cc4af7', 'job_69face9de2a33',
        'job_69fad0ff94448', 'job_1778045626_5681', 'job_1778046030_3536',
        'job_1778076523_9885', 'job_1778076937_9555', 'job_1778410944_4579',
        'job_1778493210_4291', 'job_1778493223_8685', 'job_1778494265_9327', 'job_1778507183_9811',
        'job_1778576320_1894', 'job_1778588750_4342', 'job_1778593189_5696',
        'job_1778834620_5689', 'job_1778979761_4539', 'job_1778980263_3282',
        'job_1778982266_1049', 'job_1778982287_8203', 'job_1778984505_2754', 'job_1779010389_3879',
        'job_1779084442_7322', 'job_1779161652_4217', 'job_1779175754_9038',
        'job_1779439658_3474', 'job_1779255626_6451', 'job_1779707492_9266',
        'job_1779783673_5327', 'job_1779440456_2447', 'job_1780225405_1344', 
        'job_1780224708_2956', 'job_1780367983_6647',
    ];
    
    $found = [];
    foreach ($data as $post) {
        $id = $post['id'] ?? '';
        if (in_array($id, $goodJobIds)) {
            $found[] = $post;
        }
    }
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['count' => count($found), 'posts' => $found], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'restore_67') {
    // Build the 67-post file:
    // 1. The 7 original posts from .bak file  
    // 2. The 35 log-mapped audio posts from current 240-post file
    // 3. Any additional posts from original that don't overlap (total = 67 → diff = ~25 more from audio posts before log started)
    
    $bakOld = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json.bak.1780372977';
    $originalBak = json_decode(file_get_contents($bakOld), true);
    $current = json_decode(file_get_contents($mainFile), true);

    $goodJobIds = [
        'job_69fa8d3e8a360', 'job_69f31b9a95a4e', 'job_69fac38cc4af7', 'job_69face9de2a33',
        'job_69fad0ff94448', 'job_1778045626_5681', 'job_1778046030_3536',
        'job_1778076523_9885', 'job_1778076937_9555', 'job_1778410944_4579',
        'job_1778493210_4291', 'job_1778493223_8685', 'job_1778494265_9327', 'job_1778507183_9811',
        'job_1778576320_1894', 'job_1778588750_4342', 'job_1778593189_5696',
        'job_1778834620_5689', 'job_1778979761_4539', 'job_1778980263_3282',
        'job_1778982266_1049', 'job_1778982287_8203', 'job_1778984505_2754', 'job_1779010389_3879',
        'job_1779084442_7322', 'job_1779161652_4217', 'job_1779175754_9038',
        'job_1779439658_3474', 'job_1779255626_6451', 'job_1779707492_9266',
        'job_1779783673_5327', 'job_1779440456_2447', 'job_1780225405_1344', 
        'job_1780224708_2956', 'job_1780367983_6647',
    ];
    
    $audioPosts = [];
    foreach ($current as $post) {
        $id = $post['id'] ?? '';
        if (in_array($id, $goodJobIds)) {
            $audioPosts[] = $post;
        }
    }
    
    // Merge: original bak + audio posts, dedup by title
    $merged = $originalBak;
    $existingTitles = array_column($originalBak, 'title');
    foreach ($audioPosts as $ap) {
        if (!in_array($ap['title'], $existingTitles)) {
            $merged[] = $ap;
            $existingTitles[] = $ap['title'];
        }
    }
    
    // Save backup of current then write merged
    copy($mainFile, $mainFile . '.bak.before67restore');
    file_put_contents($mainFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Restored " . count($merged) . " posts (7 original + " . count($audioPosts) . " audio-analyzed)";
}
