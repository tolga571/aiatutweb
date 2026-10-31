<?php
namespace App\Src;

/**
 * Dodo Payments counterpart of FastSpringBilling / the Paddle webhook's
 * plan-granting logic. Applies a subscription's current state to a user the
 * same way regardless of which webhook event triggered it — Dodo's own
 * `subscription.updated` fires on every field change, so treating each
 * event as "here is the subscription's current truth" (not a delta) is both
 * simpler and safer than reasoning about each event type individually.
 *
 * Internal plan_status values stay the same as Paddle/FastSpring:
 * 'starter', 'pro', 'active' (= Premium), plus 'canceled' once deactivated.
 */
class DodoBilling
{
    private Database $db;
    private array $config;
    private ?DodoClient $client;

    public function __construct(Database $db, array $config, ?DodoClient $client = null)
    {
        $this->db = $db;
        $this->config = $config;
        $this->client = $client;
    }

    /** product_id => [plan_status, billing interval] */
    public function productMap(): array
    {
        $paths = $this->config['dodo_product_paths'] ?? [];
        $map = [];
        foreach (['starter', 'pro', 'active'] as $plan) {
            foreach (['month', 'year'] as $interval) {
                $id = $paths[$plan][$interval] ?? '';
                if ($id !== '') {
                    $map[$id] = [$plan, $interval];
                }
            }
        }
        return $map;
    }

    public function productIdFor(string $plan, string $interval): string
    {
        return $this->config['dodo_product_paths'][$plan][$interval] ?? '';
    }

    /**
     * Finds which user a subscription belongs to: the metadata.user_id set
     * at checkout time (see DodoClient::createCheckoutSession), falling
     * back to an already-linked subscription id for later events that
     * might not carry metadata.
     */
    public function findUserId(array $sub): ?int
    {
        $tagUserId = $sub['metadata']['user_id'] ?? null;
        if (is_numeric($tagUserId)) {
            $row = $this->db->fetchOne('SELECT id FROM users WHERE id = ?', [(int)$tagUserId]);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $subId = (string)($sub['subscription_id'] ?? '');
        if ($subId !== '') {
            $row = $this->db->fetchOne('SELECT id FROM users WHERE dodo_subscription_id = ?', [$subId]);
            if ($row) {
                return (int)$row['id'];
            }
        }

        return null;
    }

    /**
     * Applies a Dodo subscription's current state to a user.
     * $eventTimestamp is the webhook-timestamp header (Unix seconds) —
     * pass null when there's no event to order against.
     * Returns a short human-readable result for logging.
     */
    public function applySubscription(int $userId, array $sub, ?int $eventTimestamp = null): string
    {
        $subId = (string)($sub['subscription_id'] ?? '');
        $productId = (string)($sub['product_id'] ?? '');
        $status = (string)($sub['status'] ?? '');
        $isActive = $status === 'active';
        $customerId = (string)($sub['customer']['customer_id'] ?? '');
        $nextBilledAt = $this->toTimestamp($sub['next_billing_date'] ?? null);

        $user = $this->db->fetchOne(
            'SELECT plan_status, pending_plan_change, dodo_subscription_id, dodo_last_event_at FROM users WHERE id = ?',
            [$userId]
        );
        if (!$user) {
            return "user {$userId} not found";
        }

        // Same out-of-order guard as FastSpringBilling: strictly-less-than
        // only, so two events that happen to share a timestamp never get
        // treated as stale — duplicate *events* are deduped by id in the
        // webhook loop instead, not here.
        $lastAppliedTs = $user['dodo_last_event_at'] !== null ? (int)$user['dodo_last_event_at'] : null;
        if ($eventTimestamp !== null && $lastAppliedTs !== null && $eventTimestamp < $lastAppliedTs) {
            return "ignored stale event (ts={$eventTimestamp}) — user {$userId} already has a newer event applied (last={$lastAppliedTs})";
        }

        $oldPlanStatus = $user['plan_status'] ?? 'inactive';

        // 'pending' = created but not paid yet: grants nothing, takes nothing away.
        if ($status === 'pending') {
            return "ignored pending subscription {$subId}";
        }

        if (!$isActive) {
            // Only deactivate the user's current subscription. A checkout
            // whose first payment failed was never linked to the user, and
            // must not end their free trial or a plan they already have.
            if (empty($user['dodo_subscription_id']) || $user['dodo_subscription_id'] !== $subId) {
                return "ignored deactivation of subscription {$subId} (not the user's current one)";
            }
            $this->db->execute(
                "UPDATE users SET plan_status = 'canceled', has_paid = 0, payment_pending_at = NULL,
                 cancel_requested_at = NULL, cancel_method = NULL, pending_plan_change = NULL, next_billed_at = NULL,
                 dodo_last_event_at = COALESCE(?, dodo_last_event_at)
                 WHERE id = ?",
                [$eventTimestamp, $userId]
            );
            $this->adjustTokenBonus($userId, $oldPlanStatus, 'canceled');
            $result = "deactivated user {$userId} (subscription {$subId}, status={$status})";
            if ($oldPlanStatus !== 'canceled' && $oldPlanStatus !== 'inactive') {
                ActivityLog::record($this->db, $userId, 'subscription_canceled', 'dodo', null, null, $result);
            }
            return $result;
        }

        $map = $this->productMap();
        if (isset($map[$productId])) {
            [$newPlan, $interval] = $map[$productId];
        } else {
            error_log("Dodo: unrecognized product_id '{$productId}' for user {$userId}, defaulting to starter");
            $newPlan = 'starter';
            $interval = 'month';
        }

        // A downgrade the user scheduled keeps their current (higher) plan
        // until the next rebill actually charges the new price.
        $pendingChange = $user['pending_plan_change'] ?? null;
        $scheduledChange = $sub['scheduled_change'] ?? null;
        $keepPendingDowngrade = !empty($pendingChange) && !empty($scheduledChange);
        if ($keepPendingDowngrade) {
            $newPlan = $oldPlanStatus;
            $interval = null;
        }

        $cancelPending = !empty($sub['cancel_at_next_billing_date']);

        $sets = [
            'plan_status = ?', 'has_paid = 1', 'payment_pending_at = NULL',
            'pending_purchase_plan = NULL', 'pending_purchase_interval = NULL',
            'dodo_subscription_id = COALESCE(?, dodo_subscription_id)', 'dodo_customer_id = COALESCE(?, dodo_customer_id)',
            'next_billed_at = COALESCE(?, next_billed_at)', 'billing_interval = COALESCE(?, billing_interval)',
            'dodo_last_event_at = COALESCE(?, dodo_last_event_at)',
        ];
        $params = [$newPlan, $subId !== '' ? $subId : null, $customerId !== '' ? $customerId : null, $nextBilledAt, $interval, $eventTimestamp];

        if (!$keepPendingDowngrade) {
            $sets[] = 'pending_plan_change = NULL';
        }
        if ($cancelPending) {
            $sets[] = 'cancel_requested_at = COALESCE(cancel_requested_at, ' . $this->db->now() . ')';
            $sets[] = "cancel_method = 'api'";
        } else {
            $sets[] = 'cancel_requested_at = NULL';
            $sets[] = 'cancel_method = NULL';
        }

        $params[] = $userId;
        $this->db->execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
        $this->adjustTokenBonus($userId, $oldPlanStatus, $newPlan);

        $result = "user {$userId} -> {$newPlan}" . ($interval ? "/{$interval}" : '') . " (status={$status}, subscription {$subId})";
        if (!$keepPendingDowngrade && $newPlan !== $oldPlanStatus) {
            $eventType = in_array($oldPlanStatus, ['inactive', 'canceled'], true)
                ? 'subscription_started'
                : (TokenManager::planRank($newPlan) > TokenManager::planRank($oldPlanStatus) ? 'subscription_upgraded' : 'subscription_downgraded');
            ActivityLog::record($this->db, $userId, $eventType, 'dodo', $newPlan, $interval, $result);
        }
        return $result;
    }

    /**
     * Sales are closed to the subscription's billing country (config
     * sales_blocked_countries): cancel it now, refund what it charged, keep
     * the user off the paid plan, and tell the owner and the user. Throws
     * when Dodo can't be reached or a refund fails, so the webhook answers
     * non-2xx and Dodo retries; refunded payments are skipped on a retry.
     */
    public function refuseBlockedCountry(?int $userId, array $sub, ?int $eventTimestamp = null): string
    {
        $subId = (string)($sub['subscription_id'] ?? '');
        $country = strtoupper((string)($sub['billing']['country'] ?? ''));
        if ($this->client === null || $subId === '') {
            throw new \RuntimeException("cannot refuse subscription '{$subId}': no Dodo client or subscription id");
        }
        $reason = "Jumplearner does not sell to customers in {$country}.";
        if (!$this->client->cancelSubscriptionNow($subId, $reason)) {
            // A second event for the same purchase finds it cancelled already.
            $current = $this->client->getSubscription($subId);
            if (($current['status'] ?? '') !== 'cancelled') {
                throw new \RuntimeException("cancelling subscription {$subId} failed");
            }
        }
        $payments = $this->client->listSubscriptionPayments($subId);
        if ($payments === null) {
            throw new \RuntimeException("listing payments of subscription {$subId} failed");
        }
        $refunded = 0;
        foreach ($payments as $p) {
            if (($p['status'] ?? '') !== 'succeeded' || !empty($p['refund_status'])) {
                continue;
            }
            if (!$this->client->refundPayment((string)($p['payment_id'] ?? ''), $reason)) {
                throw new \RuntimeException('refunding payment ' . ($p['payment_id'] ?? '?') . " of subscription {$subId} failed");
            }
            $refunded++;
        }

        // The plan is never granted. If this subscription was somehow linked
        // already, take it back the same way a cancellation does.
        $user = $userId ? $this->db->fetchOne('SELECT email, name, plan_status, dodo_subscription_id, ui_lang, native_lang FROM users WHERE id = ?', [$userId]) : null;
        if (!$user && !empty($sub['customer']['email'])) {
            // Not matched to an account: still tell the payer.
            $user = ['email' => (string)$sub['customer']['email'], 'ui_lang' => null, 'native_lang' => null, 'dodo_subscription_id' => null, 'plan_status' => null];
        }
        if ($userId && $user && ($user['dodo_subscription_id'] ?? '') === $subId) {
            $this->db->execute(
                "UPDATE users SET plan_status = 'canceled', has_paid = 0, cancel_requested_at = NULL, cancel_method = NULL,
                 pending_plan_change = NULL, next_billed_at = NULL WHERE id = ?",
                [$userId]
            );
            $this->adjustTokenBonus($userId, (string)$user['plan_status'], 'canceled');
        }
        if ($userId) {
            $this->db->execute(
                'UPDATE users SET payment_pending_at = NULL, pending_purchase_plan = NULL, pending_purchase_interval = NULL,
                 dodo_last_event_at = COALESCE(?, dodo_last_event_at) WHERE id = ?',
                [$eventTimestamp, $userId]
            );
        }

        $result = "refused subscription {$subId} for user " . ($userId ?: 'unmatched') . ": billing country {$country}; cancelled, refunded {$refunded} payment(s)";
        $already = $refunded === 0 && $this->db->fetchOne(
            "SELECT 1 FROM activity_events WHERE event_type = 'sale_refused_country' AND detail LIKE ?",
            ['refused subscription ' . $subId . ' %']
        );
        if ($already) {
            return "subscription {$subId} already refused (billing country {$country})";
        }
        ActivityLog::record($this->db, $userId, 'sale_refused_country', 'dodo', null, null, $result);
        $this->mailRefused($user, $result, $refunded);
        return $result;
    }

    /** Owner + customer e-mails for refuseBlockedCountry(); a failed e-mail never undoes the refund. */
    private function mailRefused(?array $user, string $result, int $refunded): void
    {
        try {
            $mailer = new Mailer($this->config);
            $owner = (string)($this->config['mail_from_address'] ?? '');
            if ($owner !== '') {
                $mailer->send($owner, 'Sale refused: blocked country',
                    '<p>' . htmlspecialchars($result) . '</p>'
                    . ($refunded === 0 ? '<p><strong>No payment was refunded — check the subscription in the Dodo dashboard.</strong></p>' : ''));
            }
            if ($user && !empty($user['email'])) {
                Language::boot($this->db);
                Language::load((string)($user['ui_lang'] ?: $user['native_lang'] ?: 'en'));
                $mailer->send((string)$user['email'], __('billing.country_refused_subject'),
                    '<p>' . __('billing.country_refused_body') . '</p>');
            }
        } catch (\Throwable $e) {
            error_log('DodoBilling::mailRefused: ' . $e->getMessage());
        }
    }

    /** Same upgrade/downgrade quota handling as Paddle/FastSpring. */
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

    /** Dodo timestamps are ISO 8601 strings, not epoch millis. */
    private function toTimestamp($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
    }
}
