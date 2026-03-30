<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/core/GeminiService.php';

// === 音声ファイルの存在確認 ===
$audioPath = $_SESSION["audio_path"] ?? '';
if (!$audioPath || !file_exists($audioPath)) {
    echo json_encode(["status" => "error", "message" => "音声ファイルが見つかりません。"]);
    exit();
}

// === 文字起こし実行 ===
try {
    $gemini = new GeminiService();
    $mimeType = mime_content_type($audioPath);
    
    // transcribe() は内部で Gemini にアップロードし、ACTIVE になるまで待機し、
    // 生成リクエストを投げて、最終的な文字列を返す。
    $raw = $gemini->transcribe($audioPath, $mimeType);
    
    $_SESSION["raw_transcription"] = $raw;
    echo json_encode(["status" => "success"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
exit();