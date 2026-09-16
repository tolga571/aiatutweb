<?php
/**
 * Dodo Payments webhook receiver (Standard Webhooks spec).
 *
 * Configure in Dodo: Developer > Webhooks (already created via API for this
 * integration — see webhook_id ep_3JPnsjAPo0dicP2DgjSiRedAbbx).
 *   URL:    https://jumplearner.com/dodo-webhook.php
 *   Secret: same value as DODO_WEBHOOK_SECRET (starts with "whsec_")
 *   Events: subscription.active, subscription.renewed, subscription.on_hold,
 *           subscription.paused, subscription.unpaused,
 *           subscription.plan_changed, subscription.cancelled,
 *           subscription.failed, subscription.expired, subscription.past_due
 *
 * Signature: Standard Webhooks — HMAC-SHA256 of "{webhook-id}.{webhook-
 * timestamp}.{raw body}", key is base64_decode(secret after "whsec_"),
 * output compared as base64. The header can carry multiple space-separated
 * "v1,<sig>" candidates (secret rotation); any match is accepted.
 */
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\DodoBilling;
use App\Src\DodoClient;

$logFile = __DIR__ . '/../data/dodo_webhook.log';
function dodoLog(string $logFile, string $message): void
{
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] {$message}\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$payload = file_get_contents('php://input');
$webhookId = $_SERVER['HTTP_WEBHOOK_ID'] ?? '';
$webhookTimestamp = $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '';
$signatureHeader = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';
$secret = $config['dodo_webhook_secret'] ?? '';

if ($payload === '' || $webhookId === '' || $webhookTimestamp === '' || $signatureHeader === '' || $secret === '') {
    dodoLog($logFile, 'VALIDATION FAILED: missing payload, webhook-id, webhook-timestamp, webhook-signature, or secret.');
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

// Replay protection, same 5-minute window as the Paddle webhook.
if (!ctype_digit($webhookTimestamp) || abs(time() - (int)$webhookTimestamp) > 300) {
    dodoLog($logFile, "VALIDATION FAILED: timestamp too old or clock drift. ts={$webhookTimestamp}");
    http_response_code(400);
    echo 'Bad Request: timestamp verification failed';
    exit;
}

$secretBytes = base64_decode(substr($secret, strlen('whsec_')), true);
if ($secretBytes === false) {
    dodoLog($logFile, 'VALIDATION FAILED: could not decode webhook secret.');
    http_response_code(500);
    echo 'Server Error';
    exit;
}
$signedContent = "{$webhookId}.{$webhookTimestamp}.{$payload}";
$expected = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

$valid = false;
foreach (explode(' ', trim($signatureHeader)) as $candidate) {
    $parts = explode(',', $candidate, 2);
    $sig = $parts[1] ?? $parts[0];
    if ($sig !== '' && hash_equals($expected, $sig)) {
        $valid = true;
        break;
    }
}
if (!$valid) {
    dodoLog($logFile, 'VALIDATION FAILED: signature mismatch.');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type']) || !isset($event['data'])) {
    http_response_code(400);
    echo 'Bad Request: invalid JSON';
    exit;
}

$type = (string)$event['type'];
$sub = is_array($event['data']) ? $event['data'] : [];
$eventTimestamp = (int)$webhookTimestamp;

dodoLog($logFile, "RECEIVED: {$type} (webhook-id={$webhookId})");

if (strpos($type, 'subscription.') !== 0) {
    dodoLog($logFile, "IGNORED {$type}: not a subscription event");
    http_response_code(200);
    echo 'OK';
    exit;
}

// Dedup by webhook-id: Dodo resends the same id on automatic retries.
// Checked here (cheap SELECT) before doing any work, but only marked done
// on actual success below — an event that failed to resolve stays
// eligible for a later retry instead of being permanently quarantined.
$db = new Database($config['db_url']);
if ($db->fetchOne('SELECT 1 FROM dodo_processed_events WHERE event_id = ?', [$webhookId])) {
    dodoLog($logFile, "DUPLICATE {$type} ({$webhookId}): already processed, skipping");
    http_response_code(200);
    echo 'OK';
    exit;
}

$client = new DodoClient($config['dodo_api_key'] ?? '', $config['dodo_environment'] ?? 'live');
$billing = new DodoBilling($db, $config, $client);

try {
    $userId = $billing->findUserId($sub);
    if (!$userId) {
        dodoLog($logFile, "UNMATCHED {$type} ({$webhookId}): no user for subscription " . ($sub['subscription_id'] ?? '?'));
        http_response_code(200);
        echo 'OK';
        exit;
    }
    $result = $billing->applySubscription($userId, $sub, $eventTimestamp);
    $db->execute('INSERT INTO dodo_processed_events (event_id) VALUES (?) ON CONFLICT (event_id) DO NOTHING', [$webhookId]);
    dodoLog($logFile, "OK {$type} ({$webhookId}): {$result}");
    http_response_code(200);
    echo 'OK';
} catch (\Throwable $e) {
    dodoLog($logFile, "ERROR {$type} ({$webhookId}): " . $e->getMessage());
    // A non-2xx response makes Dodo retry later with the same webhook-id;
    // applySubscription's writes are idempotent, so a retry is safe.
    http_response_code(500);
    echo 'Error';
}
