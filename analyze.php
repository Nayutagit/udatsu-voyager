<?php
session_start();

// === Whisper APIキー ===
$apiKey = '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';

function whisperTranscribe($audioPath) {
    global $apiKey;

    $mimeType = mime_content_type($audioPath);
    $fileName = basename($audioPath);
    $boundary = "B" . uniqid();
    $eol = "\r\n";
    $fileData = file_get_contents($audioPath);

    $body  = "--$boundary$eol";
    $body .= 'Content-Disposition: form-data; name="model"' . $eol . $eol . 'whisper-1' . $eol;
    $body .= "--$boundary$eol";
    $body .= 'Content-Disposition: form-data; name="response_format"' . $eol . $eol . 'text' . $eol;
    $body .= "--$boundary$eol";
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . $eol;
    $body .= 'Content-Type: ' . $mimeType . $eol . $eol;
    $body .= $fileData . $eol;
    $body .= "--$boundary--$eol";

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: multipart/form-data; boundary=$boundary"
    ];

    $ch = curl_init("https://api.openai.com/v1/audio/transcriptions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response ?: "(Whisper APIの応答が取得できませんでした)";
}

// === 音声ファイルの存在確認 ===
$audioPath = $_SESSION["audio_path"] ?? '';
if (!$audioPath || !file_exists($audioPath)) {
    // エラー画面へ（ファイル消失時など）
    header("Location: error_file_size.php?plan=unknown&limit=不明");
    exit();
}

// === 文字起こし実行 ===
$raw = whisperTranscribe($audioPath);
$_SESSION["raw_transcription"] = $raw;

// === 結果ページへ（1本化）===
header("Location: result.php");
exit();