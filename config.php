<?php
return [
    // SQLite database file path
    'db_path' => __DIR__ . '/data/aiut.db',
    // Gemini API key – replace with your own key or set in .env
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: 'AIzaSyBkEISDbR545jV3fq7ZaTH3lCN6x_TqMrw',
    // Daily token limit per user
    'daily_token_limit' => 1000,
    // Flag to indicate whether a user has paid (stub implementation)
    'payment_required' => true,

    'paddle_premium_price_id' => getenv('PADDLE_PREMIUM_PLAN_PRICE_ID'),
    'paddle_webhook_secret'   => getenv('PADDLE_WEBHOOK_SECRET'),
    'paddle_starter_price_id' => getenv('PADDLE_STARTER_PLAN_PRICE_ID'),
    'paddle_pro_price_id'     => getenv('PADDLE_PRO_PLAN_PRICE_ID'),

    'paddle_client_token'     => getenv('PADDLE_CLIENT_TOKEN'),
    'paddle_environment'      => getenv('PADDLE_ENVIRONMENT') ?: 'sandbox',
];
$paddlePremiumPriceId = $config['paddle_premium_price_id'] ?? '';
?>
