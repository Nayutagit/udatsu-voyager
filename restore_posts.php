<?php
if (($_GET['key'] ?? '') !== 'udatsu2026diag') { http_response_code(403); exit('Forbidden'); }

$userPostsFile = __DIR__ . '/users/YvD0Ab05RJZxSeNEAqAgurEzMEh2_posts.json';

// Read JSON input from POST body
$input = file_get_contents('php://input');
if (empty($input)) {
    exit("No input data received");
}

$decoded = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit("Invalid JSON: " . json_last_error_msg());
}

if (!is_array($decoded)) {
    exit("Input is not a JSON array");
}

// Backup existing if any
if (file_exists($userPostsFile)) {
    copy($userPostsFile, $userPostsFile . '.bak.' . time());
}

$success = file_put_contents($userPostsFile, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($success === false) {
    exit("Failed to write posts file");
}

echo "Successfully wrote " . count($decoded) . " posts (" . $success . " bytes) to " . basename($userPostsFile);
