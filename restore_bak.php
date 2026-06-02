<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$dir = __DIR__ . '/users/';
$mainFile = $dir . 'YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';
$bakFile  = $dir . 'YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json.bak.1780372977';

$action = $_GET['action'] ?? 'inspect';

if ($action === 'inspect') {
    // Show summary of backup contents
    if (!file_exists($bakFile)) {
        exit("Backup file not found: " . basename($bakFile));
    }
    $data = json_decode(file_get_contents($bakFile), true);
    if (!is_array($data)) {
        exit("Backup is not valid JSON");
    }
    header('Content-Type: application/json; charset=UTF-8');
    $summary = [];
    foreach ($data as $p) {
        $summary[] = [
            'id'     => $p['id'] ?? '(none)',
            'title'  => $p['title'] ?? '(no title)',
            'status' => $p['status'] ?? '',
            'audio'  => $p['audio_file'] ?? $p['audio'] ?? '',
            'date'   => $p['date'] ?? '',
        ];
    }
    echo json_encode(['count' => count($data), 'posts' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} elseif ($action === 'restore') {
    // Restore: overwrite main file with backup
    if (!file_exists($bakFile)) {
        exit("Backup file not found");
    }
    // Save current reconstructed as a separate bak
    if (file_exists($mainFile)) {
        copy($mainFile, $mainFile . '.bak.reconstructed');
    }
    copy($bakFile, $mainFile);
    $data = json_decode(file_get_contents($mainFile), true);
    echo "Restored. Post count: " . count($data);
}
