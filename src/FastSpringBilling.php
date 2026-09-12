<?php
namespace App\Src;

/**
 * FastSpring counterpart of the plan-granting logic in public/webhook.php
 * (Paddle). Shared by the FastSpring webhook endpoint and the post-checkout
 * order verification route so both apply a subscription to a user the same
 * way.
 *
 * Internal plan_status values stay the same as with Paddle:
 * 'starter', 'pro', 'active' (= Premium), plus 'canceled' once deactivated.
 */
class FastSpringBilling
{
    private Database $db;
    private array $config;
    private ?FastSpringClient $client;

    public function __construct(Database $db, array $config, ?FastSpringClient $client = null)
    {
        $this->db = $db;
        $this->config = $config;
        $this->client = $client;
    }

    /** product path => [plan_status, billing interval] */
    public function productMap(): array
    {
        $paths = $this->config['fastspring_product_paths'] ?? [];
        $map = [];
        foreach (['starter', 'pro', 'active'] as $plan) {
            foreach (['month', 'year'] as $interval) {
                $path = $paths[$plan][$interval] ?? '';
                if ($path !== '') {
                    $map[$path] = [$plan, $interval];
                }
            }
        }
        return $map;
    }

    public function pathFor(string $plan, string $interval): string
    {
        return $this->config['fastspring_product_paths'][$plan][$interval] ?? '';
    }

    public function isLiveMode(): bool
    {
        return ($this->config['fastspring_environment'] ?? 'test') === 'live';
    }

    /**
     * In live mode, ignore test orders/subscriptions (placed with the test
     * card) so they can never grant real access. In test mode, accept both.
     */
    public function acceptsLiveFlag($live): bool
    {
        if (!$this->isLiveMode()) {
            return true;
        }
        return $live === true || $live === 'true' || $live === 1;
    }

    // ── Payload helpers (fields are IDs, or objects with webhook expansion) ──

    public static function productPath($product): string
    {
        if (is_string($product)) {
            return $product;
        }
        if (is_array($product)) {
            return (string)($product['product'] ?? $product['path'] ?? $product['id'] ?? '');
        }
        return '';
    }

    public static function subscriptionId(array $sub): string
    {
        return (string)($sub['id'] ?? $sub['subscription'] ?? '');
    }

    public static function accountId($account): string
    {
        if (is_string($account)) {
            return $account;
        }
        if (is_array($account)) {
            return (string)($account['id'] ?? $account['account'] ?? '');
        }
        return '';
    }

    public static function accountEmail($account): string
    {
        if (is_array($account)) {
            return strtolower(trim((string)($account['contact']['email'] ?? $account['email'] ?? '')));
        }
        return '';
    }

    /** FastSpring timestamps are milliseconds since epoch. */
    private static function msToTimestamp($ms): ?string
    {
        if (!is_numeric($ms) || (float)$ms <= 0) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', (int)floor((float)$ms / 1000));
    }

    /**
     * Resolves the subscription object from an event's data. Charge events
     * nest it under `subscription` (an ID unless webhook expansion is on).
     */
    public function subscriptionFromEvent(string $type, array $data): ?array
    {
        if (strpos($type, 'subscription.charge.') === 0 || strpos($type, 'subscription.payment.') === 0) {
            $sub = $data['subscription'] ?? null;
            if (is_array($sub)) {
                return $sub;
            }
            if (is_string($sub) && $sub !== '' && $this->client && $this->client->isConfigured()) {
                return $this->client->getSubscription($sub);
            }
            return null;
        }
        return $data;
    }

    /**
     * Finds which user a subscription belongs to, most reliable signal first:
     * an already-linked subscription ID, a user_id tag from checkout, the
     * linked FastSpring account ID, then the checkout email as a last resort.
     */
    public function findUserId(array $sub): ?int
    {
        $subId = self::subscriptionId($sub);
        if ($subId !== '') {
            $row = $this->db->fetchOne('SELECT id FROM users WHERE fastspring_subscription_id = ?', [$subId]);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $tagUserId = $sub['tags']['user_id'] ?? null;
        if (is_numeric($tagUserId)) {
            $row = $this->db->fetchOne('SELECT id FROM users WHERE id = ?', [(int)$tagUserId]);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $accountId = self::accountId($sub['account'] ?? null);
        if ($accountId !== '') {
            $row = $this->db->fetchOne('SELECT id FROM users WHERE fastspring_account_id = ?', [$accountId]);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $email = self::accountEmail($sub['account'] ?? null);
        if ($email !== '') {
            $rows = $this->db->fetchAll('SELECT id FROM users WHERE LOWER(email) = ?', [$email]);
            if (count($rows) === 1) {
                return (int)$rows[0]['id'];
            }
        }

        return null;
    }

    /**
     * Applies a FastSpring subscription's current state to a user.
     * $eventCreatedMs is the webhook envelope's `created` (ms) timestamp —
     * pass null when there is no event to order against (the direct
     * post-checkout order-verification path isn't part of the event
     * stream, so there's nothing to be stale relative to).
     * Returns a short human-readable result for logging.
     */
    public function applySubscription(int $userId, array $sub, string $eventType, ?int $eventCreatedMs = null): string
    {
        $subId = self::subscriptionId($sub);
        $path = self::productPath($sub['product'] ?? null);
        $state = (string)($sub['state'] ?? '');
        $isActive = array_key_exists('active', $sub)
            ? ($sub['active'] === true || $sub['active'] === 'true')
            : ($state !== '' && $state !== 'deactivated');
        $accountId = self::accountId($sub['account'] ?? null);
        $nextBilledAt = self::msToTimestamp($sub['next'] ?? null);

        $user = $this->db->fetchOne(
            'SELECT plan_status, pending_plan_change, cancel_method, fastspring_subscription_id, fastspring_last_event_at FROM users WHERE id = ?',
            [$userId]
        );
        if (!$user) {
            return "user {$userId} not found";
        }

        // Webhook delivery order isn't guaranteed to match send order once
        // retries/network jitter are in play. If a fresher event already
        // moved this user's state forward, an older one arriving late must
        // not roll it back (e.g. a delayed subscription.activated retry
        // undoing a subscription.deactivated that already landed).
        // Strictly-less-than only: different event types for the same
        // purchase (e.g. order.completed and subscription.activated) can
        // legitimately share the same millisecond `created` value, and a
        // tie must never be treated as stale — that's not this check's job,
        // duplicate *events* are deduped by id in the webhook loop instead.
        $lastAppliedMs = $user['fastspring_last_event_at'] !== null ? (int)$user['fastspring_last_event_at'] : null;
        if ($eventCreatedMs !== null && $lastAppliedMs !== null && $eventCreatedMs < $lastAppliedMs) {
            return "ignored stale event {$eventType} (created={$eventCreatedMs}) — user {$userId} already has a newer event applied (last={$lastAppliedMs})";
        }

        $oldPlanStatus = $user['plan_status'] ?? 'inactive';

        if (!$isActive) {
            // Only deactivate if this is still the user's current
            // subscription — not an old one they already replaced.
            if (!empty($user['fastspring_subscription_id']) && $user['fastspring_subscription_id'] !== $subId) {
                return "ignored deactivation of old subscription {$subId}";
            }
            $this->db->execute(
                "UPDATE users SET plan_status = 'canceled', has_paid = 0, payment_pending_at = NULL,
                 cancel_requested_at = NULL, cancel_method = NULL, pending_plan_change = NULL, next_billed_at = NULL,
                 fastspring_last_event_at = COALESCE(?, fastspring_last_event_at)
                 WHERE id = ?",
                [$eventCreatedMs, $userId]
            );
            $this->adjustTokenBonus($userId, $oldPlanStatus, 'canceled');
            return "deactivated user {$userId} (subscription {$subId})";
        }

        $map = $this->productMap();
        if (isset($map[$path])) {
            [$newPlan, $interval] = $map[$path];
        } else {
            // Unrecognized product — don't silently grant the top tier.
            error_log("FastSpring: unrecognized product path '{$path}' for user {$userId}, defaulting to starter");
            $newPlan = 'starter';
            $interval = 'month';
        }

        // A downgrade the user scheduled keeps their current (higher) plan
        // until the next rebill actually charges the new price.
        $pendingChange = $user['pending_plan_change'] ?? null;
        $keepPendingDowngrade = !empty($pendingChange)
            && !in_array($eventType, ['subscription.charge.completed', 'subscription.activated'], true);
        if ($keepPendingDowngrade) {
            $newPlan = $oldPlanStatus;
            $interval = null;
        }

        $sets = [
            'plan_status = ?', 'has_paid = 1', 'payment_pending_at = NULL',
            'pending_purchase_plan = NULL', 'pending_purchase_interval = NULL',
            'fastspring_subscription_id = COALESCE(?, fastspring_subscription_id)', 'fastspring_account_id = COALESCE(?, fastspring_account_id)',
            'next_billed_at = COALESCE(?, next_billed_at)', 'billing_interval = COALESCE(?, billing_interval)',
            'fastspring_last_event_at = COALESCE(?, fastspring_last_event_at)',
        ];
        $params = [$newPlan, $subId !== '' ? $subId : null, $accountId !== '' ? $accountId : null, $nextBilledAt, $interval, $eventCreatedMs];

        if (!$keepPendingDowngrade) {
            $sets[] = 'pending_plan_change = NULL';
        }
        if ($state === 'canceled') {
            // Cancellation scheduled for the end of the period (by the user
            // in-app, from the FastSpring account portal, or by support).
            $sets[] = 'cancel_requested_at = COALESCE(cancel_requested_at, ' . $this->db->now() . ')';
            $sets[] = "cancel_method = 'api'";
        } elseif ($state === 'active' || $state === 'trial') {
            $sets[] = 'cancel_requested_at = NULL';
            $sets[] = 'cancel_method = NULL';
        }

        $params[] = $userId;
        $this->db->execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
        $this->adjustTokenBonus($userId, $oldPlanStatus, $newPlan);

        return "user {$userId} -> {$newPlan}" . ($interval ? "/{$interval}" : '') . " (state={$state}, subscription {$subId})";
    }

    /** Same upgrade/downgrade quota handling as the Paddle webhook. */
    private function adjustTokenBonus(int $userId, string $oldPlan, string $newPlan): void
    {
        $oldRank = TokenManager::planRank($oldPlan);
        $newRank = TokenManager::planRank($newPlan);
        if ($newRank > $oldRank) {
            $this->db->execute('UPDATE token_usage SET bonus_limit = 0 WHERE user_id = ?', [$userId]);
        } elseif ($newRank < $oldRank) {
            $oldLimit = (new TokenManager($this->db))->getBaseLimit($oldPlan);
            $this->db->execute('UPDATE token_usage SET bonus_limit = bonus_limit + ? WHERE user_id = ?', [$oldLimit, $userId]);
        }
    }

    /**
     * Verifies an order the browser reports as completed, and grants the
     * plan it bought. Returns ['ok' => bool, 'error' => ?string].
     * Nothing is granted from client input alone — every fact comes from
     * the FastSpring API, AND the order must carry the tags.user_id set at
     * checkout creation (see views/pricing.php) matching the caller. Without
     * that check, any logged-in user could pass another real customer's
     * order id (leaked via a screenshot, receipt, referral link, etc.) and
     * steal their paid plan — the order's completed/unlinked state alone
     * isn't proof of who paid for it.
     */
    public function verifyOrderForUser(int $userId, string $orderId): array
    {
        if (!$this->client || !$this->client->isConfigured()) {
            return ['ok' => false, 'error' => 'api_not_configured'];
        }
        $order = $this->client->getOrder($orderId);
        if (!$order) {
            return ['ok' => false, 'error' => 'order_not_found'];
        }
        $completed = $order['completed'] ?? false;
        if (!($completed === true || $completed === 'true')) {
            return ['ok' => false, 'error' => 'order_not_completed'];
        }
        if (!$this->acceptsLiveFlag($order['live'] ?? null)) {
            return ['ok' => false, 'error' => 'test_order_in_live_mode'];
        }

        $orderTagUserId = $order['tags']['user_id'] ?? null;
        if (!is_numeric($orderTagUserId) || (int)$orderTagUserId !== $userId) {
            error_log("FastSpring: order {$orderId} tags.user_id (" . var_export($orderTagUserId, true) . ") does not match requesting user {$userId} — refusing to grant.");
            return ['ok' => false, 'error' => 'order_not_owned'];
        }

        $map = $this->productMap();
        $orderPaths = [];
        foreach ($order['items'] ?? [] as $item) {
            $p = self::productPath($item['product'] ?? null);
            if (isset($map[$p])) {
                $orderPaths[] = $p;
            }
        }
        if (!$orderPaths) {
            return ['ok' => false, 'error' => 'no_plan_in_order'];
        }

        $accountId = self::accountId($order['account'] ?? null);
        if ($accountId === '') {
            return ['ok' => false, 'error' => 'no_account'];
        }

        // Find the active subscription this order created.
        $match = null;
        foreach ($this->client->getSubscriptionsForAccount($accountId) as $sub) {
            $isActive = ($sub['active'] ?? false) === true || ($sub['active'] ?? '') === 'true';
            if ($isActive && in_array(self::productPath($sub['product'] ?? null), $orderPaths, true)) {
                $match = $sub;
                break;
            }
        }
        if (!$match) {
            return ['ok' => false, 'error' => 'subscription_not_found'];
        }

        $subId = self::subscriptionId($match);
        $owner = $this->db->fetchOne('SELECT id FROM users WHERE fastspring_subscription_id = ? AND id <> ?', [$subId, $userId]);
        if ($owner) {
            return ['ok' => false, 'error' => 'subscription_already_linked'];
        }
        $accountOwner = $this->db->fetchOne('SELECT id FROM users WHERE fastspring_account_id = ? AND id <> ?', [$accountId, $userId]);
        if ($accountOwner) {
            return ['ok' => false, 'error' => 'account_already_linked'];
        }

        if (!isset($match['account']) || !is_array($match['account'])) {
            $match['account'] = $accountId;
        }
        $this->applySubscription($userId, $match, 'subscription.activated');
        return ['ok' => true, 'error' => null];
    }
}
