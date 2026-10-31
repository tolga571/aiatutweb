<?php
// Sales closed to Türkiye: DodoBilling::refuseBlockedCountry() with a fake
// Dodo client (no real API calls, no e-mails), against the local staging DB,
// plus the checkout guard through the local server with a CF-IPCountry header.
// usage: php tests/e2e/tr_sales_test.php   (server on :8090, DATABASE_URL in .env)
require __DIR__ . '/../../autoload.php';
$config = require __DIR__ . '/../../config.php';
$config['mailtrap_api_token'] = ''; // never send mail from a test

use App\Src\Database;
use App\Src\DodoBilling;
use App\Src\DodoClient;

class FakeDodo extends DodoClient
{
    public array $calls = [];
    public bool $cancelOk = true;
    public array $payments = [];
    public function __construct() { parent::__construct('fake', 'test'); }
    public function cancelSubscriptionNow(string $subscriptionId, string $reason = ''): bool { $this->calls[] = "cancel {$subscriptionId}"; return $this->cancelOk; }
    public function getSubscription(string $subscriptionId): ?array { $this->calls[] = "get {$subscriptionId}"; return ['status' => 'cancelled']; }
    public function listSubscriptionPayments(string $subscriptionId): ?array { $this->calls[] = "list {$subscriptionId}"; return $this->payments; }
    public function refundPayment(string $paymentId, string $reason = ''): bool { $this->calls[] = "refund {$paymentId}"; return true; }
}

$n = $fails = 0;
function ok($got, $want, string $name): void
{
    global $n, $fails;
    $n++;
    if ($got === $want) { echo "PASS {$name}\n"; } else { $fails++; echo 'FAIL ' . $name . ' (got ' . var_export($got, true) . ' want ' . var_export($want, true) . ")\n"; }
}

$db = new Database($config['db_url']);
$tag = hrtime(true);
$db->execute("INSERT INTO users (email, password, name, plan_status, has_paid, onboarding_completed, native_lang, target_lang, payment_pending_at, pending_purchase_plan)
              VALUES (?, 'x', 'TR test', 'trial', 0, 1, 'en', 'es', now(), 'pro')", ["trsales{$tag}@example.test"]);
$uid = (int)$db->fetchOne('SELECT id FROM users WHERE email = ?', ["trsales{$tag}@example.test"])['id'];
$sub = ['subscription_id' => "sub_tr_{$tag}", 'status' => 'active', 'billing' => ['country' => 'TR'], 'customer' => ['email' => "trsales{$tag}@example.test"]];

ok($config['sales_blocked_countries'], ['TR'], 'default blocked countries = TR');

$fake = new FakeDodo();
$fake->payments = [
    ['payment_id' => 'pay_ok', 'status' => 'succeeded'],
    ['payment_id' => 'pay_done', 'status' => 'succeeded', 'refund_status' => 'full'],
    ['payment_id' => 'pay_failed', 'status' => 'failed'],
];
$billing = new DodoBilling($db, $config, $fake);
$r = $billing->refuseBlockedCountry($uid, $sub, time());
ok($fake->calls, ["cancel sub_tr_{$tag}", "list sub_tr_{$tag}", 'refund pay_ok'], 'cancels now and refunds only the unrefunded successful payment');
ok(str_contains($r, 'refunded 1 payment'), true, 'result says one refund');
$u = $db->fetchOne('SELECT plan_status, has_paid, payment_pending_at, pending_purchase_plan, dodo_subscription_id FROM users WHERE id = ?', [$uid]);
ok([$u['plan_status'], (int)$u['has_paid'], $u['payment_pending_at'], $u['pending_purchase_plan'], $u['dodo_subscription_id']], ['trial', 0, null, null, null], 'user keeps the trial, pending purchase cleared, nothing linked');
ok((int)$db->fetchOne("SELECT COUNT(*) AS c FROM activity_events WHERE user_id = ? AND event_type = 'sale_refused_country'", [$uid])['c'], 1, 'refusal logged once');

// A second event for the same purchase: Dodo says it is cancelled, nothing left to refund.
$fake2 = new FakeDodo();
$fake2->cancelOk = false;
$fake2->payments = [['payment_id' => 'pay_ok', 'status' => 'succeeded', 'refund_status' => 'full']];
$r2 = (new DodoBilling($db, $config, $fake2))->refuseBlockedCountry($uid, $sub, time());
ok(str_contains($r2, 'already refused'), true, 'repeat event: already refused, no second refund');
ok((int)$db->fetchOne("SELECT COUNT(*) AS c FROM activity_events WHERE user_id = ? AND event_type = 'sale_refused_country'", [$uid])['c'], 1, 'repeat event not logged again');

// A user who already had the plan linked to this subscription loses it.
$db->execute("UPDATE users SET plan_status = 'pro', has_paid = 1, dodo_subscription_id = ? WHERE id = ?", ["sub_tr2_{$tag}", $uid]);
$fake3 = new FakeDodo();
(new DodoBilling($db, $config, $fake3))->refuseBlockedCountry($uid, ['subscription_id' => "sub_tr2_{$tag}", 'status' => 'active', 'billing' => ['country' => 'TR']], time());
$u = $db->fetchOne('SELECT plan_status, has_paid FROM users WHERE id = ?', [$uid]);
ok([$u['plan_status'], (int)$u['has_paid']], ['canceled', 0], 'a linked TR subscription is taken back');

// Unmatched payer (no account): still cancelled and refunded.
$fake4 = new FakeDodo();
$fake4->payments = [['payment_id' => 'pay_x', 'status' => 'succeeded']];
(new DodoBilling($db, $config, $fake4))->refuseBlockedCountry(null, ['subscription_id' => "sub_tr3_{$tag}", 'status' => 'active', 'billing' => ['country' => 'TR']], time());
ok($fake4->calls, ["cancel sub_tr3_{$tag}", "list sub_tr3_{$tag}", 'refund pay_x'], 'unmatched payer: cancelled and refunded');

// Dodo unreachable: throws so the webhook answers non-2xx and Dodo retries.
$fake5 = new class extends FakeDodo { public function listSubscriptionPayments(string $s): ?array { return null; } };
try { (new DodoBilling($db, $config, $fake5))->refuseBlockedCountry($uid, ['subscription_id' => "sub_tr4_{$tag}", 'status' => 'active', 'billing' => ['country' => 'TR']], time()); $thrown = false; }
catch (\RuntimeException $e) { $thrown = true; }
ok($thrown, true, 'Dodo unreachable -> throws (webhook retries)');

// Checkout guard through the local server.
$base = getenv('BASE') ?: 'http://127.0.0.1:8090/';
$sid = "trsales{$tag}";
$db->execute("INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, now() + interval '1 hour')", [$sid, "user_id|i:{$uid};"]);
$get = function (string $url, string $country) use ($sid): array {
    $ctx = stream_context_create(['http' => ['header' => "Cookie: PHPSESSID={$sid}\r\n" . ($country !== '' ? "CF-IPCountry: {$country}\r\n" : ''), 'follow_location' => 0, 'ignore_errors' => true]]);
    $body = (string)@file_get_contents($url, false, $ctx);
    $loc = '';
    foreach ($http_response_header ?? [] as $h) { if (stripos($h, 'Location:') === 0) { $loc = trim(substr($h, 9)); } }
    return [$body, $loc];
};
[, $loc] = $get($base . '?page=dodo-checkout&plan=pro&interval=month', 'TR');
ok($loc, '?page=pricing&sales_closed=1', 'checkout from TR -> back to pricing, no Dodo session');
[$body] = $get($base . '?page=pricing', 'TR');
ok(str_contains($body, 'id="sales-closed-note"'), true, 'pricing from TR shows the closed notice');
[$body] = $get($base . '?page=pricing', 'DE');
ok(str_contains($body, 'id="sales-closed-note"'), false, 'pricing from DE has no notice');
[$body] = $get($base . '?page=terms-and-conditions', '');
ok(str_contains($body, 'dodopayments.com/legal/buyer-terms'), true, 'terms link Dodo buyer terms');

$db->execute('DELETE FROM sessions WHERE id = ?', [$sid]);
echo "TOTAL {$n}  PASS " . ($n - $fails) . "  FAIL {$fails}\n";
exit($fails ? 1 : 0);
