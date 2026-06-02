<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$action = $_GET['action'] ?? 'restore_67';

$mainFile  = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';
$bakOld    = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json.bak.1780372977';

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

// Also include the old job IDs that existed before the log started tracking
// These come from the very first "37" entries in the SAVED log entries
// They had job IDs like job_69f... from the older cron format
$oldAudioPaths = [
    'uploads/line_612698103136387175_1778027838.m4a',
    'uploads/line_611879642022281575_1777539994.m4a',
    'uploads/line_612721423701508560_1778041740.m4a',
    'uploads/line_612726186652467558_1778044573.m4a',
    'uploads/line_612727154110628295_1778045183.m4a',
    'uploads/line_612727924805599595_1778045626.m4a',
    'uploads/line_612728613090885650_1778046030.m4a',
    'uploads/line_612779775999606904_1778076523.m4a',
    'uploads/line_612780481984856150_1778076937.m4a',
    'uploads/line_613340852843970926_1778410944.m4a',
    'uploads/line_613478895609446469_1778493223.m4a',
    'uploads/line_613480640993558699_1778494265.m4a',
    'uploads/line_613502317676724310_1778507183.m4a',
    'uploads/line_613618295450894763_1778576320.m4a',
    'uploads/line_613639155989348559_1778588750.m4a',
    'uploads/line_613646611498664209_1778593189.m4a',
    'uploads/line_614051661760954489_1778834620.m4a',
    'uploads/line_614295169360986317_1778979761.m4a',
    'uploads/line_614296012987040205_1778980263.m4a',
    'uploads/line_614299409870225427_1778982287.m4a',
    'uploads/line_614299409870225427_1778982266.m4a',
    'uploads/line_614303129160647117_1778984505.m4a',
    'uploads/line_614346557587718642_1779010389.m4a',
    'uploads/line_614470795204231645_1779084442.m4a',
    'uploads/line_614600301034340804_1779161652.m4a',
    'uploads/line_614623993399345502_1779175754.m4a',
    'uploads/line_615066740052721894_1779439658.m4a',
    'uploads/line_614757992050458799_1779255626.m4a',
    'uploads/line_615068075988877879_1779440456.m4a',
    'uploads/line_615516097633845593_1779707492.m4a',
    'uploads/line_615643911968260590_1779783673.m4a',
    'uploads/line_616383845561795168_1780224708.m4a',
    'uploads/line_616385016225595823_1780225405.m4a',
    'uploads/line_616624218070581453_1780367983.m4a',
];

$current = json_decode(file_get_contents($mainFile), true);
$originalBak = json_decode(file_get_contents($bakOld), true);

// Collect audio posts: by jobId or by audio path
$audioPosts = [];
$seenAudios = [];
foreach ($current as $post) {
    $id   = $post['id'] ?? '';
    $audio = $post['audio_file'] ?? $post['audio'] ?? '';
    
    $isGoodJob = in_array($id, $goodJobIds);
    $isGoodAudio = in_array($audio, $oldAudioPaths);
    
    if (($isGoodJob || $isGoodAudio) && !in_array($audio, $seenAudios)) {
        $audioPosts[] = $post;
        $seenAudios[] = $audio;
    }
}

// Merge: original bak + audio posts, dedup by title  
$merged = $originalBak;
$existingTitles = array_column($originalBak, 'title');
$existingIds = array_column($originalBak, 'id');

foreach ($audioPosts as $ap) {
    $title = $ap['title'] ?? '';
    $apId  = $ap['id'] ?? '';
    if (!in_array($title, $existingTitles) && !in_array($apId, $existingIds)) {
        $merged[] = $ap;
        $existingTitles[] = $title;
        $existingIds[] = $apId;
    }
}

if ($action === 'restore_67') {
    // Save backup of current then write merged
    copy($mainFile, $mainFile . '.bak.before67restore.' . time());
    file_put_contents($mainFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Restored " . count($merged) . " posts (7 original + " . count($audioPosts) . " audio-analyzed). Total: " . count($merged);
} else {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['found_audio_posts' => count($audioPosts), 'total_after_merge' => count($merged), 'titles' => array_column($audioPosts, 'title')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
