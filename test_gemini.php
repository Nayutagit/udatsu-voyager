<?php
// test_gemini.php
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/GeminiService.php';

if (($_GET['key'] ?? '') !== 'udatsu2026test') {
    exit('Forbidden');
}

echo "<pre>";
echo "Testing Gemini Connection...\n";

// Load key
require __DIR__ . '/config/gemini_key.php';
if (!isset($geminiApiKey)) {
    exit("API Key not found.");
}
echo "API Key loaded: " . substr($geminiApiKey, 0, 5) . "...\n";

// Let's make a mock upload request or simple generateContent request to verify key
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiApiKey}";
$postData = [
    "contents" => [
        [
            "parts" => [
                ["text" => "Hello, say test."]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n" . htmlspecialchars($response) . "\n";

echo "=== Mock Upload test ===\n";
$urlUpload = "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$geminiApiKey}";
$headers = [
    "X-Goog-Upload-Protocol: resumable",
    "X-Goog-Upload-Command: start",
    "X-Goog-Upload-Header-Content-Length: 10",
    "X-Goog-Upload-Header-Content-Type: text/plain",
    "Content-Type: application/json"
];
$data = json_encode(["file" => ["display_name" => "test.txt"]]);

$ch = curl_init($urlUpload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$resUpload = curl_exec($ch);
$httpCodeUpload = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Upload HTTP Code: $httpCodeUpload\n";
echo "Upload Response:\n" . htmlspecialchars($resUpload) . "\n";
echo "</pre>";
