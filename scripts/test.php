<?php
require 'autoload.php';
$config = require 'config.php';

$apiKey = $config['gemini_api_key'] ?? 'missing';
echo "Using API Key starting with: " . substr($apiKey, 0, 10) . "...\n";

$client = new App\Src\GeminiClient($apiKey);

try {
    $history = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'ai', 'content' => 'Hi there!', 'translation' => 'Merhaba!']
    ];
    
    $response = $client->chatWithHistory('How are you?', $history, 'You are a helpful assistant', 'en');
    echo "Success: " . substr($response, 0, 100) . "...";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
