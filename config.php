<?php
$dbUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL');

return [
    // Database (SQLite fallback, PostgreSQL via DATABASE_URL env)
    'db_path'   => __DIR__ . '/data/aiut.db',
    'db_url'    => $dbUrl ?: '',

    // Gemini API key – set in .env
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'daily_token_limit' => 1000,
    'payment_required' => true,

    'paddle_premium_price_id' => getenv('PADDLE_PREMIUM_PLAN_PRICE_ID'),
    'paddle_webhook_secret'   => getenv('PADDLE_WEBHOOK_SECRET'),
    'paddle_starter_price_id' => getenv('PADDLE_STARTER_PLAN_PRICE_ID'),
    'paddle_pro_price_id'     => getenv('PADDLE_PRO_PLAN_PRICE_ID'),

    'paddle_client_token'     => getenv('PADDLE_CLIENT_TOKEN'),
    'paddle_environment'      => getenv('PADDLE_ENVIRONMENT') ?: 'sandbox',

    // Google Sign-In Client ID
    'google_client_id'        => getenv('GOOGLE_CLIENT_ID') ?: '',
];
