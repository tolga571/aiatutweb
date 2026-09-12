<?php
/**
 * FastSpring webhook receiver.
 *
 * Configure in FastSpring: Developer Tools > Webhooks
 *   URL:    https://jumplearner.com/fastspring-webhook.php
 *   Secret: same value as FASTSPRING_WEBHOOK_SECRET
 *   Events: subscription.activated, subscription.updated, subscription.canceled,
 *           subscription.uncanceled, subscription.deactivated,
 *           subscription.charge.completed, subscription.charge.failed
 *   Turn on "webhook expansion" so account/product come as full objects.
 */
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\FastSpringBilling;
use App\Src\FastSpringClient;

$logFile = __DIR__ . '/../data/fastspring_webhook.log';
function fsLog(string $logFile, string $message): void
{
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] {$message}\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FS_SIGNATURE'] ?? '';
$secret = $config['fastspring_webhook_secret'] ?? '';

// 1. Every request must be signed: base64(HMAC-SHA256(raw body, secret)).
if ($payload === '' || $signature === '' || $secret === '') {
    fsLog($logFile, 'VALIDATION FAILED: missing payload, signature or secret. signature_present=' . ($signature === '' ? 'no' : 'yes') . ' secret_present=' . ($secret === '' ? 'no' : 'yes'));
    http_response_code(400);
    echo 'Bad Request';
    exit;
}
$expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
if (!hash_equals($expected, $signature)) {
    fsLog($logFile, 'VALIDATION FAILED: signature mismatch.');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$body = json_decode($payload, true);
if (!is_array($body) || !isset($body['events']) || !is_array($body['events'])) {
    http_response_code(400);
    echo 'Bad Request: invalid JSON';
    exit;
}

$db = new Database($config['db_url']);
$client = new FastSpringClient($config['fastspring_api_username'] ?? '', $config['fastspring_api_password'] ?? '');
$billing = new FastSpringBilling($db, $config, $client);

$hadError = false;
foreach ($body['events'] as $event) {
    $eventId = (string)($event['id'] ?? '');
    $type = (string)($event['type'] ?? '');
    $data = is_array($event['data'] ?? null) ? $event['data'] : [];

    if (!$billing->acceptsLiveFlag($event['live'] ?? null)) {
        fsLog($logFile, "IGNORED {$type} {$eventId}: test event while in live mode");
        continue;
    }

    if (strpos($type, 'subscription.') !== 0) {
        fsLog($logFile, "IGNORED {$type} {$eventId}: not a subscription event");
        continue;
    }

    if (in_array($type, ['subscription.charge.failed', 'subscription.payment.overdue', 'subscription.payment.reminder', 'subscription.trial.reminder'], true)) {
        // Informational — FastSpring handles dunning emails and will send
        // subscription.deactivated if the payment is never recovered.
        fsLog($logFile, "NOTED {$type} {$eventId}");
        continue;
    }

    // Dedup by event id: FastSpring resends the same id on automatic
    // retries (a manual retry gets a new id, which is fine — it represents
    // "process this again on purpose"). The created-timestamp check inside
    // applySubscription answers a different question (is this event older
    // than one already applied) and must not also be used for this.
    // Checked here (cheap SELECT) to skip real duplicates before spending an
    // API call on subscriptionFromEvent, but only marked done further below
    // on actual success — an event that failed to resolve (e.g. because API
    // credentials weren't configured yet) must still be eligible for a
    // later retry to succeed, not permanently quarantined.
    if ($eventId !== '' && $db->fetchOne('SELECT 1 FROM fastspring_processed_events WHERE event_id = ?', [$eventId])) {
        fsLog($logFile, "DUPLICATE {$type} {$eventId}: already processed, skipping");
        continue;
    }

    try {
        $sub = $billing->subscriptionFromEvent($type, $data);
        if (!$sub) {
            fsLog($logFile, "IGNORED {$type} {$eventId}: could not resolve subscription (enable webhook expansion or set API credentials)");
            continue;
        }
        $userId = $billing->findUserId($sub);
        if (!$userId) {
            fsLog($logFile, "UNMATCHED {$type} {$eventId}: no user for subscription " . FastSpringBilling::subscriptionId($sub));
            continue;
        }
        $eventCreatedMs = isset($event['created']) && is_numeric($event['created']) ? (int)$event['created'] : null;
        $result = $billing->applySubscription($userId, $sub, $type, $eventCreatedMs);
        if ($eventId !== '') {
            // A concurrent duplicate delivery could race past the SELECT
            // check above and reach here too — harmless, since
            // applySubscription's own writes are idempotent either way, and
            // ON CONFLICT here just means the marker was already inserted.
            $db->execute('INSERT INTO fastspring_processed_events (event_id) VALUES (?) ON CONFLICT (event_id) DO NOTHING', [$eventId]);
        }
        fsLog($logFile, "OK {$type} {$eventId}: {$result}");
    } catch (\Throwable $e) {
        $hadError = true;
        fsLog($logFile, "ERROR {$type} {$eventId}: " . $e->getMessage());
    }
}

// A non-2xx response makes FastSpring retry the whole batch later (same
// event IDs). Every update above is idempotent, so a retry is safe.
if ($hadError) {
    http_response_code(500);
    echo 'Error';
    exit;
}
http_response_code(200);
echo 'OK';
