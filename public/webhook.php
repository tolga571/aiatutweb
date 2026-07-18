<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\PaddleIpAllowlist;

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

// 0. In live mode, reject anything not sourced from Paddle's published live
// IP ranges (https://api.paddle.com/ips), fetched and cached rather than
// hardcoded since that list can change. Sandbox is left unrestricted so
// local/staging testing against the sandbox webhook keeps working.
if (($config['paddle_environment'] ?? 'sandbox') === 'production') {
    $requestIp = client_ip();
    $ipCacheFile = __DIR__ . '/../data/paddle_live_ips.json';
    if (!PaddleIpAllowlist::isAllowed($requestIp, $ipCacheFile)) {
        logWebhook($logFile, "REJECTED: Request IP not in Paddle's live allowlist.", "ip={$requestIp}");
        http_response_code(403);
        echo "Forbidden: Source IP not allowed.";
        exit;
    }
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
        // Only set when $hasPaid resolves a real price_id below; left null
        // otherwise so the UPDATE's COALESCE leaves the existing value alone.
        $billingInterval = null;

        if ($hasPaid) {
            // Check the price ID to determine the specific plan tier. Each
            // tier can have both a monthly and a yearly Price in Paddle
            // (same product, same plan_status/quota) — match against either.
            $priceId = $data['items'][0]['price']['id'] ?? '';
            $starterPriceIds = array_filter([$config['paddle_starter_price_id'] ?? '', $config['paddle_starter_yearly_price_id'] ?? '']);
            $proPriceIds = array_filter([$config['paddle_pro_price_id'] ?? '', $config['paddle_pro_yearly_price_id'] ?? '']);
            $premiumPriceIds = array_filter([$config['paddle_premium_price_id'] ?? '', $config['paddle_premium_yearly_price_id'] ?? '']);

            if (in_array($priceId, $proPriceIds, true)) {
                $planStatus = 'pro';
            } elseif (in_array($priceId, $starterPriceIds, true)) {
                $planStatus = 'starter';
            } elseif (in_array($priceId, $premiumPriceIds, true)) {
                $planStatus = 'active';
            } else {
                // Unrecognized price ID — don't silently grant the top tier.
                // Default to the lowest paid tier and log it loudly so a
                // misconfigured PADDLE_*_PRICE_ID gets caught instead of
                // quietly over-granting access.
                $planStatus = 'starter';
                logWebhook($logFile, "WARNING: Unrecognized price_id '{$priceId}' for User ID {$userId} — defaulting to 'starter', verify PADDLE_*_PRICE_ID config.");
            }

            $yearlyPriceIds = array_filter([
                $config['paddle_starter_yearly_price_id'] ?? '',
                $config['paddle_pro_yearly_price_id'] ?? '',
                $config['paddle_premium_yearly_price_id'] ?? '',
            ]);
            $billingInterval = in_array($priceId, $yearlyPriceIds, true) ? 'year' : 'month';
        }

        try {
            // Check old plan status
            $userRow = $db->fetchOne('SELECT plan_status FROM users WHERE id = ?', [$userId]);
            $oldPlanStatus = $userRow ? ($userRow['plan_status'] ?? 'inactive') : 'inactive';

            // Keep the Paddle subscription/customer IDs on file so the app can
            // later call the Paddle API to cancel or change this subscription
            // in place, instead of opening a brand-new checkout.
            $subscriptionId = $data['id'] ?? null;
            $customerId = $data['customer_id'] ?? null;
            $nextBilledAt = $data['next_billed_at'] ?? null;

            // A subscription that's scheduled to cancel (or change price) at
            // period end is still 'active' in Paddle's eyes (with a
            // scheduled_change) until that date arrives, so this handler
            // runs again for it before then. Only clear our own
            // cancel_requested_at / pending_plan_change flags once there is
            // no scheduled change left pending (either it was resumed, or it
            // fully took effect) — otherwise the "Cancel Subscription"
            // button, or the pending-downgrade notice, would reappear/vanish
            // incorrectly right after the user actually completed the action.
            $hasScheduledChange = !empty($data['scheduled_change']);
            if ($hasScheduledChange) {
                $db->execute(
                    'UPDATE users SET plan_status = ?, has_paid = ?, payment_pending_at = NULL,
                     paddle_subscription_id = COALESCE(?, paddle_subscription_id), paddle_customer_id = COALESCE(?, paddle_customer_id),
                     next_billed_at = COALESCE(?, next_billed_at), billing_interval = COALESCE(?, billing_interval)
                     WHERE id = ?',
                    [$planStatus, $hasPaid, $subscriptionId, $customerId, $nextBilledAt, $billingInterval, $userId]
                );
            } else {
                $db->execute(
                    'UPDATE users SET plan_status = ?, has_paid = ?, payment_pending_at = NULL, cancel_requested_at = NULL, cancel_method = NULL, pending_plan_change = NULL,
                     paddle_subscription_id = COALESCE(?, paddle_subscription_id), paddle_customer_id = COALESCE(?, paddle_customer_id),
                     next_billed_at = COALESCE(?, next_billed_at), billing_interval = COALESCE(?, billing_interval)
                     WHERE id = ?',
                    [$planStatus, $hasPaid, $subscriptionId, $customerId, $nextBilledAt, $billingInterval, $userId]
                );
            }

            // Handle Upgrade / Downgrade for Monthly limits
            $oldRank = \App\Src\TokenManager::planRank($oldPlanStatus);
            $newRank = \App\Src\TokenManager::planRank($planStatus);

            if ($newRank > $oldRank) {
                // Upgrade: Reset bonus
                $db->execute('UPDATE token_usage SET bonus_limit = 0 WHERE user_id = ?', [$userId]);
                logWebhook($logFile, "UPGRADE: Reset bonus limit for User ID {$userId}");
            } elseif ($newRank < $oldRank) {
                // Downgrade: Add old plan's base limit to bonus limit
                $oldLimit = (new \App\Src\TokenManager($db))->getBaseLimit($oldPlanStatus);
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
