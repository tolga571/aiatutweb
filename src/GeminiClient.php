<?php
namespace App\Src;

class GeminiClient {
    private array $apiKeys;
    private array $models = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
    ];

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private string $lastError = '';

    public function __construct(string $primaryKey, string $backupKey = '') {
        $keys = [$primaryKey];
        if ($backupKey) {
            $keys[] = $backupKey;
        }
        $this->apiKeys = $keys;
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function chat(string $prompt): string {
        return $this->chatWithHistory($prompt, [], '');
    }

    public function chatWithHistory(string $message, array $history, string $systemPrompt, string $targetLang = 'en'): string {
        $contents = [];

        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $text = $role === 'model'
                ? json_encode(['content' => $msg['content'], 'translation' => $msg['translation'] ?? ''])
                : $msg['content'];
            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $payload = ['contents' => $contents];
        if ($systemPrompt) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }
        $payload['generationConfig'] = ['responseMimeType' => 'application/json'];

        $lastError = null;
        foreach ($this->apiKeys as $key) {
            foreach ($this->models as $model) {
                try {
                    $url = $this->baseUrl . $model . ':generateContent?key=' . urlencode($key);
                    $response = $this->httpPost($url, $payload);
                    $data = json_decode($response, true);
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($text) return $text;
                } catch (\Exception $e) {
                    $lastError = $e;
                }
            }
        }

        $errorMsg = 'Gemini API unavailable: ' . ($lastError?->getMessage() ?? 'unknown error');
        error_log($errorMsg);
        throw new \RuntimeException($errorMsg);
    }

    private function httpPost(string $url, array $data): string {
        $json = json_encode($data);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json),
            ],
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            $this->lastError = "cURL error ({$errno}): {$error}";
            throw new \RuntimeException($this->lastError);
        }

        if ($httpCode !== 200) {
            $this->lastError = "Gemini API returned HTTP {$httpCode}: " . substr($result, 0, 200);
            throw new \RuntimeException($this->lastError);
        }

        return $result;
    }
}
