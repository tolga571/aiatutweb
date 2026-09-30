<?php
namespace App\Src;

class GeminiClient {
    private array $apiKeys;
    // Tried in order. The fallback is Flash-Lite, not Pro: when Flash is
    // overloaded, Pro (~4x Flash's price) is usually slow too, while
    // Flash-Lite answers fast at a fraction of the cost.
    private array $models = [
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
    ];

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private string $lastError = '';
    private ?array $lastUsage = null;

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

    /**
     * Token usage of the last successful call: model, prompt_tokens,
     * output_tokens, thought_tokens. Null if no model answered.
     */
    public function getLastUsage(): ?array {
        return $this->lastUsage;
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
        // Thinking off: thought tokens bill at the output rate and were up to
        // half of a reply's cost, with no difference in the corrections
        // (compared on the real tutor prompt, 2026-09-30).
        $payload['generationConfig'] = [
            'responseMimeType' => 'application/json',
            'thinkingConfig' => ['thinkingBudget' => 0],
        ];

        $errors = [];
        $this->lastUsage = null;
        foreach ($this->apiKeys as $ki => $key) {
            $keyLabel = 'key' . ($ki + 1);
            foreach ($this->models as $model) {
                $maxRetries = 2;
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $url = $this->baseUrl . $model . ':generateContent?key=' . urlencode($key);
                        $response = $this->httpPost($url, $payload);
                        $data = json_decode($response, true);
                        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        if ($text) {
                            $meta = $data['usageMetadata'] ?? [];
                            $this->lastUsage = [
                                'model' => $model,
                                'prompt_tokens' => (int)($meta['promptTokenCount'] ?? 0),
                                'output_tokens' => (int)($meta['candidatesTokenCount'] ?? 0),
                                'thought_tokens' => (int)($meta['thoughtsTokenCount'] ?? 0),
                            ];
                            return $text;
                        }
                    } catch (\Exception $e) {
                        $errMsg = $e->getMessage();
                        if ($attempt < $maxRetries && (str_contains($errMsg, 'HTTP 503') || str_contains($errMsg, 'cURL error (28)'))) {
                            usleep(2000000); // 2 seconds
                            continue;
                        }
                        $errors[] = "{$keyLabel}/{$model}: {$errMsg}";
                        break;
                    }
                }
            }
        }

        $errorMsg = 'Gemini API unavailable: ' . implode(' | ', $errors);
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
            CURLOPT_TIMEOUT => 30,
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
