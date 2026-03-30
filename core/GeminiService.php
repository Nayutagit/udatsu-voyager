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
        
        $maxRetries = 60; // 60 seconds max
        $lastResponse = "";
        
        for ($i = 0; $i < $maxRetries; $i++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $lastResponse = $response;
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                // If the check itself fails (e.g. Rate limit, High Demand)
                $errMsg = $data['error']['message'] ?? 'Unknown API Error';
                throw new Exception("サーバー混雑または制限エラー: " . $errMsg);
            }
            
            $state = $data['state'] ?? 'UNKNOWN';
            
            if ($state === 'ACTIVE') {
                return;
            }
            if ($state === 'FAILED') {
                throw new Exception("Geminiでの音声ファイル処理が失敗しました。");
            }
            
            sleep(1);
        }
        throw new Exception("タイムアウトしました。APIからの最後の応答: " . $lastResponse);
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
        
        $maxRetries = 3;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return "文字起こし中に通信エラーが発生しました: " . $curlError;
            }
            
            $data = json_decode($response, true);
            
            // 成功時
            if ($httpCode >= 200 && $httpCode < 300 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
            // APIエラー（503 high demand等）の場合は再試行
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? 'Unknown error';
                // 400系なら再試行しても無駄なことが多いが、500/503は再試行する
                if ($httpCode >= 500 || strpos(strtolower($errorMsg), 'demand') !== false || strpos(strtolower($errorMsg), 'quota') !== false) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep(2 * $attempt); // Exponential backoff (2s, 4s, 6s...)
                        continue;
                    }
                    return "AIサーバーが混雑しています（" . $errorMsg . "）。時間をおいて再度アップロードしてください。";
                }
                return "APIエラーが発生しました: " . $errorMsg;
            }
            
            return "予期せぬレスポンスが返されました。";
        }
        
        return "サーバー混雑により文字起こしを完了できませんでした。後ほど再試行してください。";
    }
}
