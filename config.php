<?php
$dbUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL');

return [
    'db_url'    => $dbUrl,

    // Gemini API keys – set in .env (primary + optional backup)
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'gemini_api_key_backup' => getenv('GEMINI_API_KEY_BACKUP') ?: '',
    'daily_token_limit' => 1000,
    'payment_required' => true,

    'paddle_premium_price_id' => getenv('PADDLE_PREMIUM_PLAN_PRICE_ID'),
    'paddle_webhook_secret'   => getenv('PADDLE_WEBHOOK_SECRET'),
    'paddle_starter_price_id' => getenv('PADDLE_STARTER_PLAN_PRICE_ID'),
    'paddle_pro_price_id'     => getenv('PADDLE_PRO_PLAN_PRICE_ID'),

    // Optional yearly variant of each plan (same product, a second Price in
    // Paddle). Left as '' when unset — every lookup below treats '' as "no
    // yearly price configured" rather than a real ID.
    'paddle_starter_yearly_price_id' => getenv('PADDLE_STARTER_YEARLY_PRICE_ID') ?: '',
    'paddle_pro_yearly_price_id'     => getenv('PADDLE_PRO_YEARLY_PRICE_ID') ?: '',
    'paddle_premium_yearly_price_id' => getenv('PADDLE_PREMIUM_YEARLY_PRICE_ID') ?: '',

    'paddle_client_token'     => getenv('PADDLE_CLIENT_TOKEN'),
    'paddle_environment'      => getenv('PADDLE_ENVIRONMENT') ?: 'sandbox',
    // Server-side Paddle API key (Developer Tools > Authentication in the Paddle
    // dashboard). Required for in-app subscription cancellation and plan changes;
    // without it those actions fall back to a manual support request.
    'paddle_api_key'          => getenv('PADDLE_API_KEY') ?: '',

    // Google Sign-In Client ID
    'google_client_id'        => getenv('GOOGLE_CLIENT_ID') ?: '',

    // Mailtrap Email Sending
    'mailtrap_api_token'      => getenv('MAILTRAP_API_TOKEN') ?: '',
    'mail_from_address'       => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@jumplearner.com',
    'mail_from_name'          => getenv('MAIL_FROM_NAME') ?: 'Jumplearner',
];
