<?php
namespace App\Src;

class GeminiClient {
    private array $apiKeys;
    // Tried in order. The fallback is a Flash-Lite model, not Pro: when
    // Flash is overloaded, Pro (~4x Flash's price) is usually slow too,
    // while Flash-Lite answers fast and costs less. gemini-2.5-flash-lite
    // is listed but refuses new projects ("no longer available to new
    // users", HTTP 404 — found 2026-10-03), so the fallback is 3.1.
    private array $models = [
        'gemini-2.5-flash',
        'gemini-3.1-flash-lite',
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

    /** Overrides the model order, e.g. Flash-Lite first for cheap bulk jobs. */
    public function useModels(array $models): void {
        if ($models) {
            $this->models = array_values($models);
        }
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
        // Local development without an API key: GEMINI_FAKE=1 (only ever set
        // in a local .env) returns a canned tutor reply so the chat UI —
        // web and app — can be exercised. Never set on Railway.
        if (getenv('GEMINI_FAKE') === '1' && str_contains($systemPrompt, '"segmented"')) {
            $this->lastUsage = ['model' => 'fake', 'prompt_tokens' => 0, 'output_tokens' => 0, 'thought_tokens' => 0];
            return json_encode([
                'content' => '¡Hola! Me llamo Kai. ¿Cómo estás hoy?',
                'phonetic' => 'ˈola me ˈʝamo kai ˈkomo esˈtas ˈoj',
                'literal_translation' => 'Hello! Myself I call Kai. How are you today?',
                'translation' => 'Hello! My name is Kai. How are you today?',
                'grammar_spotlight' => '**llamarse (ʝaˈmaɾse)** is a reflexive verb: **me llamo (me ˈʝamo)** = "my name is".',
                'pro_tip' => 'Spanish questions start with an upside-down question mark (¿).',
                'correction' => mb_strlen($message) < 4 ? '' : 'A small fix: "Hola, yo soy Ali" instead of "Hola, yo es Ali".',
                'corrections' => mb_strlen($message) < 4 ? [] : [['original' => 'yo es', 'corrected' => 'yo soy', 'pronunciation' => 'ʝo soj', 'rule' => 'With "yo", the verb ser is "soy".']],
                'segmented' => [
                    ['text' => '¡Hola!', 'pronunciation' => 'ˈola', 'translation' => 'hello'],
                    ['text' => 'Me', 'pronunciation' => 'me', 'translation' => 'myself'],
                    ['text' => 'llamo', 'pronunciation' => 'ˈʝamo', 'translation' => 'I call'],
                    ['text' => 'Kai.', 'pronunciation' => 'kai', 'translation' => 'Kai'],
                    ['text' => '¿Cómo', 'pronunciation' => 'ˈkomo', 'translation' => 'how'],
                    ['text' => 'estás', 'pronunciation' => 'esˈtas', 'translation' => 'are you'],
                    ['text' => 'hoy?', 'pronunciation' => 'ˈoj', 'translation' => 'today'],
                ],
                'words' => [
                    ['word' => 'llamarse', 'pronunciation' => 'ʝaˈmaɾse', 'definition' => 'to be called'],
                    ['word' => 'hoy', 'pronunciation' => 'ˈoj', 'definition' => 'today'],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
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
            // Caps one reply's cost (~$0.01 of output at 2.5 Flash prices).
            // Normal replies are well under this; it only stops runaways.
            'maxOutputTokens' => 4096,
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


    public function generateContextForWord(string $word, string $targetLang, string $nativeLang): array {
        $prompt = "You are a language teacher. The user is learning '$targetLang' and their native language is '$nativeLang'. 
Create a realistic, short dialogue or sentence using the word '$word'. 
Return a JSON object with this exact structure:
{
  \"text\": \"the full text/dialogue in target language\",
  \"translation\": \"full translation in native language\",
  \"tokens\": [
    { \"token\": \"word1\", \"pinyin\": \"pronunciation1\", \"translation\": \"meaning1\" }
  ]
}
Make sure 'tokens' breaks down the entire text into words (including punctuation as separate tokens if necessary, or just skip punctuation) so the UI can render them word by word.";

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'thinkingConfig' => ['thinkingBudget' => 0]
            ]
        ];

        foreach ($this->apiKeys as $ki => $key) {
            foreach ($this->models as $model) {
                try {
                    $url = $this->baseUrl . $model . ':generateContent?key=' . urlencode($key);
                    $response = $this->httpPost($url, $payload);
                    $data = json_decode($response, true);
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($text) {
                        return json_decode($text, true) ?: [];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        return [];
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
