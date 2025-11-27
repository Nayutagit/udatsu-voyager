<?php
session_start();

// 🔸 Gemini APIキー読み込み
require_once __DIR__ . '/config/gemini_key.php';
$apiKey = $geminiApiKey;

function uploadToGemini($filePath, $mimeType) {
    global $apiKey;
    $fileSize = filesize($filePath);
    $displayName = basename($filePath);

    // 1. Start Upload Session
    $url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key=$apiKey";
    $headers = [
        "X-Goog-Upload-Protocol: resumable",
        "X-Goog-Upload-Command: start",
        "X-Goog-Upload-Header-Content-Length: $fileSize",
        "X-Goog-Upload-Header-Content-Type: $mimeType",
        "Content-Type: application/json"
    ];
    $data = json_encode(["file" => ["display_name" => $displayName]]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    curl_close($ch);

    if (!preg_match('/x-goog-upload-url: (.*)\r\n/i', $responseHeaders, $matches)) {
        return ["error" => "Failed to get upload URL"];
    }
    $uploadUrl = trim($matches[1]);

    // 2. Upload File
    $fileData = file_get_contents($filePath);
    $headers = [
        "Content-Length: $fileSize",
        "X-Goog-Upload-Offset: 0",
        "X-Goog-Upload-Command: upload, finalize"
    ];

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['file']['uri'])) {
        return ["uri" => $result['file']['uri']];
    }
    return ["error" => "Failed to upload file: " . ($result['error']['message'] ?? 'Unknown error')];
}

function geminiTranscribe($audioPath) {
    global $apiKey;

    $mimeType = mime_content_type($audioPath);
    
    // Upload first
    $uploadResult = uploadToGemini($audioPath, $mimeType);
    if (isset($uploadResult['error'])) {
        return "アップロードエラー: " . $uploadResult['error'];
    }
    $fileUri = $uploadResult['uri'];

    // Generate Content (Transcribe)
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-001:generateContent?key=$apiKey";
    
    $postData = [
        "contents" => [
            [
                "parts" => [
                    [
                        "file_data" => [
                            "mime_type" => $mimeType,
                            "file_uri" => $fileUri
                        ]
                    ],
                    ["text" => "この音声を文字起こししてください。"]
                ]
            ]
        ]
    ];

    $headers = ["Content-Type: application/json"];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return "Curl error: " . curl_error($ch);
    }
    curl_close($ch);

    $data = json_decode($response, true);
    
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "文字起こしエラーが発生しました（Gemini）。";
}

// === 音声ファイルの存在確認 ===
$audioPath = $_SESSION["audio_path"] ?? '';
if (!$audioPath || !file_exists($audioPath)) {
    // エラー画面へ（ファイル消失時など）
    header("Location: error_file_size.php?plan=unknown&limit=不明");
    exit();
}

// === 文字起こし実行 ===
$raw = geminiTranscribe($audioPath);
$_SESSION["raw_transcription"] = $raw;

// === 結果ページへ（1本化）===
header("Location: result.php");
exit();