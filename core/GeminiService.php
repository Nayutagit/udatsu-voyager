<?php
/**
 * GeminiService.php
 * Handles interactions with the Google Gemini API for transcription.
 */

class GeminiService {
    private $apiKey;

    public function __construct() {
        // Load API key
        require __DIR__ . '/../config/gemini_key.php';
        if (!isset($geminiApiKey)) {
            throw new Exception("Gemini API Key not found in config.");
        }
        $this->apiKey = $geminiApiKey;
    }

    /**
     * Uploads a file to Gemini and transcribes it.
     *
     * @param string $filePath Absolute path to the audio file.
     * @param string $mimeType MIME type of the file.
     * @return string The transcription text.
     * @throws Exception If upload or generation fails.
     */
    public function transcribe($filePath, $mimeType) {
        // 1. Upload File
        $fileUri = $this->uploadToGemini($filePath, $mimeType);

        // 2. Wait for Processing
        $this->waitForFileActive($fileUri);

        // 3. Generate Content
        return $this->generateContent($fileUri, $mimeType);
    }

    private function uploadToGemini($filePath, $mimeType) {
        $fileSize = filesize($filePath);
        $displayName = basename($filePath);

        // Start Upload Session
        $url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$this->apiKey}";
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
            throw new Exception("Failed to get upload URL from Gemini.");
        }
        $uploadUrl = trim($matches[1]);

        // Upload File Content
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
            return $result['file']['uri'];
        }
        
        $errorMsg = $result['error']['message'] ?? 'Unknown upload error';
        throw new Exception("Failed to upload file to Gemini: " . $errorMsg);
    }

    private function waitForFileActive($fileUri) {
        $url = "https://generativelanguage.googleapis.com/v1beta/files/" . basename($fileUri) . "?key={$this->apiKey}";
        
        $maxRetries = 30; // 30 seconds max
        for ($i = 0; $i < $maxRetries; $i++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            $state = $data['state'] ?? 'UNKNOWN';
            
            if ($state === 'ACTIVE') {
                return;
            }
            if ($state === 'FAILED') {
                throw new Exception("Gemini file processing failed.");
            }
            
            sleep(1);
        }
        throw new Exception("Gemini file processing timed out.");
    }

    private function generateContent($fileUri, $mimeType) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}";
        
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
                        ["text" => "この音声ファイルの発言内容を、日本語で正確に文字起こししてください。\n\n【指示】\n・「えー」「あー」などの不要なフィラーは削除してください。\n・句読点を適切に補い、読みやすい文章にしてください。\n・要約ではなく、発言内容そのものを書き起こしてください。\n・音声が聞き取れない場合は、その部分を（聞き取り不能）としてください。"]
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
            throw new Exception("Curl error: " . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);
        
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "文字起こしエラーが発生しました（Gemini）。";
    }
}
