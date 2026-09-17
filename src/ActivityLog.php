<?php
namespace App\Src;

/**
 * Records one row per meaningful billing/account event, for the admin
 * activity monitor. Deliberately not called on every webhook delivery —
 * only on state transitions that actually mean something happened (a new
 * subscription, a plan change, a cancellation) — so the feed reads like an
 * event log, not a webhook-retry log.
 */
class ActivityLog
{
    public static function record(
        Database $db,
        ?int $userId,
        string $eventType,
        ?string $provider,
        ?string $plan,
        ?string $billingInterval,
        string $detail
    ): void {
        $db->execute(
            'INSERT INTO activity_events (user_id, event_type, provider, plan, billing_interval, detail) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $eventType, $provider, $plan, $billingInterval, $detail]
        );
    }
}
