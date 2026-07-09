<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

// Open connection to database
$db = new Database($config['db_path'], $config['db_url']);

// Get raw request payload and signature header
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';
$secretKey = $config['paddle_webhook_secret'];

// Log file for webhooks
$logFile = __DIR__ . '/../data/paddle_webhook.log';

// 1. Basic validation
if (empty($payload) || empty($signature) || empty($secretKey)) {
    http_response_code(400);
    echo "Bad Request: Missing payload, signature, or webhook secret key.";
    exit;
}

// 2. Parse ts and h1 from signature header
if (!preg_match('/^ts=(\d+);h1=(.+)$/', $signature, $matches)) {
    http_response_code(400);
    echo "Bad Request: Invalid signature format.";
    exit;
}

$ts = (int)$matches[1];
$h1 = $matches[2];

// 3. Verify timestamp is within 5 minutes to prevent replay attacks
if (abs(time() - $ts) > 300) {
    http_response_code(400);
    echo "Bad Request: Signature timestamp verification failed (clock drift or replay attack).";
    exit;
}

// 4. Compute expected HMAC-SHA256 signature
$computed = hash_hmac('sha256', "{$ts}:{$payload}", $secretKey);

// 5. Compare signatures securely
if (!hash_equals($computed, $h1)) {
    http_response_code(401);
    echo "Unauthorized: Signature verification failed.";
    exit;
}

// 6. Decode the JSON body
$event = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo "Bad Request: Invalid JSON payload.";
    exit;
}

$eventType = $event['event_type'] ?? '';
$data = $event['data'] ?? [];

// Log raw payload for troubleshooting
file_put_contents(
    $logFile,
    "[" . date('Y-m-d H:i:s') . "] RECEIVED: Event Type: {$eventType} | Raw: {$payload}\n",
    FILE_APPEND
);

// 7. Handle subscription events
if (strpos($eventType, 'subscription.') === 0) {
    $status = $data['status'] ?? '';
    $customData = $data['custom_data'] ?? [];
    $userId = isset($customData['user_id']) ? (int)$customData['user_id'] : null;

    if ($userId) {
        // Map status to plan status and paid flag
        // Paddle Billing statuses: 'active', 'trialing', 'past_due', 'paused', 'canceled'
        $hasPaid = ($status === 'active' || $status === 'trialing') ? 1 : 0;
        $planStatus = $status;

        if ($hasPaid) {
            // Check the price ID to determine the specific plan tier
            $priceId = $data['items'][0]['price']['id'] ?? '';
            $starterPriceId = $config['paddle_starter_price_id'] ?? '';
            $proPriceId = $config['paddle_pro_price_id'] ?? '';

            if ($priceId === $proPriceId) {
                $planStatus = 'pro';
            } elseif ($priceId === $starterPriceId) {
                $planStatus = 'starter';
            } else {
                $planStatus = 'active';
            }
        }

        try {
            $db->execute(
                'UPDATE users SET plan_status = ?, has_paid = ? WHERE id = ?',
                [$planStatus, $hasPaid, $userId]
            );

            file_put_contents(
                $logFile,
                "[" . date('Y-m-d H:i:s') . "] SUCCESS: Updated User ID {$userId} to Status: {$planStatus}, has_paid: {$hasPaid}\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            file_put_contents(
                $logFile,
                "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
            http_response_code(500);
            echo "Internal Server Error: Database update failed.";
            exit;
        }
    } else {
        file_put_contents(
            $logFile,
            "[" . date('Y-m-d H:i:s') . "] IGNORED: No user_id found in custom_data.\n",
            FILE_APPEND
        );
    }
} else {
    file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] IGNORED: Event type '{$eventType}' is not subscription related.\n",
        FILE_APPEND
    );
}

// Respond with 200 OK to acknowledge receipt
http_response_code(200);
echo "OK";
?>
