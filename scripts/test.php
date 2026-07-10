<?php
require 'vendor/autoload.php';

$client = new App\Src\GeminiClient('AIzaSyBkEISDbR545jV3fq7ZaTH3lCN6x_TqMrw');

try {
    $history = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'ai', 'content' => 'Hi there!', 'translation' => 'Merhaba!']
    ];
    
    $response = $client->chatWithHistory('How are you?', $history, 'You are a helpful assistant', 'en');
    echo "Success: " . $response;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
