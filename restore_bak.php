<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$mainFile  = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';
$logFile   = __DIR__ . '/log/analysis_log.txt';

// Read current posts (240-post reconstructed set)
$current = json_decode(file_get_contents($mainFile), true);

// Read log to find all DONE events with timestamps
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$uid = 'YvD0Ab05RJZxSeNEAqAgurEzMEh2';
$jobStarts = [];  // job_id -> [audio, start_dt]
$donePairs = [];  // ordered: [done_dt, title]

// Cutoff: just after 11:59:33 save on 2026-06-02
$cutoff = strtotime('2026-06-02 12:00:00');

foreach ($lines as $line) {
    if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $tsMatch)) continue;
    $ts = strtotime($tsMatch[1]);
    if ($ts > $cutoff) break;
    
    if (strpos($line, "uid=$uid") !== false && strpos($line, '[run_analysis_for_post]') !== false) {
        if (preg_match('/audioPath=([^\s]+)\s+jobId=([a-zA-Z0-9_\-]+)/', $line, $m)) {
            $jobStarts[$m[2]] = ['audio' => $m[1], 'ts' => $ts];
        }
    } elseif (strpos($line, ' - DONE: ') !== false) {
        $title = explode(' - DONE: ', $line)[1];
        $donePairs[] = [$ts, trim($title)];
    }
}

// Match DONE to JOB
$matched = [];
$usedDone = [];
foreach ($jobStarts as $jobId => $jobData) {
    foreach ($donePairs as $i => [$doneDt, $title]) {
        if (isset($usedDone[$i])) continue;
        $diff = $doneDt - $jobData['ts'];
        if ($diff >= 0 && $diff < 600) {
            $matched[$jobId] = ['audio' => $jobData['audio'], 'title' => $title, 'done_ts' => $doneDt];
            $usedDone[$i] = true;
            break;
        }
    }
}

// Dedup by audio path (keep latest)
$byAudio = [];
foreach ($matched as $jobId => $data) {
    $a = $data['audio'];
    if (!isset($byAudio[$a]) || $data['done_ts'] > $byAudio[$a]['done_ts']) {
        $byAudio[$a] = array_merge($data, ['job_id' => $jobId]);
    }
}

// Now find those audio paths in current 240-post data
$audioPathsWanted = array_keys($byAudio);
$audioFound = [];
foreach ($current as $post) {
    $postAudio = $post['audio_file'] ?? $post['audio'] ?? '';
    if (in_array($postAudio, $audioPathsWanted)) {
        $audioFound[$postAudio] = $post;
    }
}

// Get original 7-post bak
$bakOld = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json.bak.1780372977';
$originalBak = json_decode(file_get_contents($bakOld), true);

// Build final 67-post array:
// Start from originalBak (7 posts)
// Then add all audio-analyzed posts (order by done_ts ascending)
uasort($byAudio, fn($a, $b) => $a['done_ts'] <=> $b['done_ts']);

$merged = $originalBak;
$mergedTitles = array_column($originalBak, 'title');
$mergedIds = array_column($originalBak, 'id');

foreach ($byAudio as $audioPath => $logData) {
    if (isset($audioFound[$audioPath])) {
        $post = $audioFound[$audioPath];
        $postId = $post['id'] ?? '';
        $postTitle = $post['title'] ?? '';
        if (!in_array($postId, $mergedIds) && !in_array($postTitle, $mergedTitles)) {
            $merged[] = $post;
            $mergedIds[] = $postId;
            $mergedTitles[] = $postTitle;
        }
    } else {
        // Not found in 240-post set by audio path - check by job_id
        $jobId = $logData['job_id'];
        foreach ($current as $post) {
            $postId = $post['id'] ?? '';
            if ($postId === $jobId && !in_array($postId, $mergedIds)) {
                $merged[] = $post;
                $mergedIds[] = $postId;
                $mergedTitles[] = $post['title'] ?? '';
                break;
            }
        }
    }
}

$action = $_GET['action'] ?? 'preview';
if ($action === 'preview') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'total_merged' => count($merged),
        'log_jobs_matched' => count($byAudio),
        'audio_found_in_current' => count($audioFound),
        'titles' => array_column($merged, 'title')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} elseif ($action === 'restore') {
    copy($mainFile, $mainFile . '.bak.before67v2.' . time());
    file_put_contents($mainFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "DONE. Restored " . count($merged) . " posts.";
}
