<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;

// Open connection to database
$db = new Database($config['db_url']);

// Get raw request payload and signature header
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';
$secretKey = $config['paddle_webhook_secret'];

// Log file for webhooks
$logFile = __DIR__ . '/../data/paddle_webhook.log';

// Helper to log errors
function logWebhook($logFile, $message, $detailed = '') {
    file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] {$message} {$detailed}\n",
        FILE_APPEND
    );
}

// 1. Basic validation
if (empty($payload) || empty($signature) || empty($secretKey)) {
    logWebhook($logFile, "VALIDATION FAILED: Missing payload, signature, or webhook secret key.",
        "payload_length=" . strlen($payload) . " signature_present=" . (empty($signature) ? 'no' : 'yes') . " secret_present=" . (empty($secretKey) ? 'no' : 'yes'));
    http_response_code(400);
    echo "Bad Request: Missing payload, signature, or webhook secret key.";
    exit;
}

// 2. Parse ts and h1 from signature header
if (!preg_match('/^ts=(\d+);h1=(.+)$/', $signature, $matches)) {
    logWebhook($logFile, "VALIDATION FAILED: Invalid signature format.", "signature=" . substr($signature, 0, 80));
    http_response_code(400);
    echo "Bad Request: Invalid signature format.";
    exit;
}

$ts = (int)$matches[1];
$h1 = $matches[2];

// 3. Verify timestamp is within 5 minutes to prevent replay attacks
if (abs(time() - $ts) > 300) {
    logWebhook($logFile, "VALIDATION FAILED: Timestamp too old or clock drift.", "ts={$ts} server_time=" . time());
    http_response_code(400);
    echo "Bad Request: Signature timestamp verification failed (clock drift or replay attack).";
    exit;
}

// 4. Compute expected HMAC-SHA256 signature
$computed = hash_hmac('sha256', "{$ts}:{$payload}", $secretKey);

// 5. Compare signatures securely
if (!hash_equals($computed, $h1)) {
    logWebhook($logFile, "VALIDATION FAILED: Signature mismatch.", "computed={$computed} h1={$h1}");
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

logWebhook($logFile, "RECEIVED: Event Type: {$eventType} | Raw:", substr($payload, 0, 500));

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
            // Check old plan status
            $userRow = $db->fetchOne('SELECT plan_status FROM users WHERE id = ?', [$userId]);
            $oldPlanStatus = $userRow ? ($userRow['plan_status'] ?? 'inactive') : 'inactive';

            $db->execute(
                'UPDATE users SET plan_status = ?, has_paid = ?, payment_pending_at = NULL WHERE id = ?',
                [$planStatus, $hasPaid, $userId]
            );

            // Handle Upgrade / Downgrade for Monthly limits
            $planRanks = ['inactive' => 0, 'trial' => 0, 'starter' => 1, 'pro' => 2, 'active' => 3];
            $planLimits = ['inactive' => 0, 'trial' => 5, 'starter' => 50, 'pro' => 500, 'active' => 1500];
            
            $oldRank = $planRanks[$oldPlanStatus] ?? 0;
            $newRank = $planRanks[$planStatus] ?? 0;

            if ($newRank > $oldRank) {
                // Upgrade: Reset bonus
                $db->execute('UPDATE token_usage SET bonus_limit = 0 WHERE user_id = ?', [$userId]);
                logWebhook($logFile, "UPGRADE: Reset bonus limit for User ID {$userId}");
            } elseif ($newRank < $oldRank) {
                // Downgrade: Add old plan's base limit to bonus limit
                $oldLimit = $planLimits[$oldPlanStatus] ?? 0;
                $db->execute('UPDATE token_usage SET bonus_limit = bonus_limit + ? WHERE user_id = ?', [$oldLimit, $userId]);
                logWebhook($logFile, "DOWNGRADE: Added {$oldLimit} to bonus limit for User ID {$userId}");
            }

            logWebhook($logFile, "SUCCESS: Updated User ID {$userId} to Status: {$planStatus}, has_paid: {$hasPaid}");
        } catch (\Exception $e) {
            logWebhook($logFile, "DATABASE ERROR: " . $e->getMessage());
            http_response_code(500);
            echo "Internal Server Error: Database update failed.";
            exit;
        }
    } else {
        logWebhook($logFile, "IGNORED: No user_id found in custom_data.");
    }
} else {
    logWebhook($logFile, "IGNORED: Event type '{$eventType}' is not subscription related.");
}

// Respond with 200 OK to acknowledge receipt
http_response_code(200);
echo "OK";
?>
