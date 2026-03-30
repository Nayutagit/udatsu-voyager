<?php
session_start();

require_once __DIR__ . '/core/GeminiService.php';

// === 音声ファイルの存在確認 ===
$audioPath = $_SESSION["audio_path"] ?? '';
if (!$audioPath || !file_exists($audioPath)) {
    // エラー画面へ（ファイル消失時など）
    header("Location: error_file_size.php?plan=unknown&limit=不明");
    exit();
}

// === 文字起こし実行 ===
try {
    $gemini = new GeminiService();
    $mimeType = mime_content_type($audioPath);
    $raw = $gemini->transcribe($audioPath, $mimeType);
    $_SESSION["raw_transcription"] = $raw;
} catch (Exception $e) {
    $_SESSION["raw_transcription"] = "エラーが発生しました: " . $e->getMessage();
}

// === 結果ページへ（1本化）===
header("Location: result.php");
exit();