<?php
namespace App\Src;

/**
 * Numbers behind the admin dashboard.
 *
 * Revenue rule: only Dodo subscriptions are real money — Dodo is the live
 * provider. Paid plans without a Dodo subscription come from Paddle sandbox
 * test checkouts (or were granted by hand), so they're counted separately as
 * "test" and never summed into revenue.
 */
class AdminStats {
    /** SQL condition for a user paying real money. */
    public const REAL_PAYING = "has_paid = 1 AND dodo_subscription_id IS NOT NULL";
    /** SQL condition for a paid plan that isn't real money (sandbox / manual). */
    public const TEST_PAYING = "has_paid = 1 AND dodo_subscription_id IS NULL";

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Headline numbers for the last $days days, each with the value for the
     * $days before that so the dashboard can show a trend.
     */
    public function kpis(int $days): array {
        $cur = "CURRENT_TIMESTAMP - INTERVAL '{$days} days'";
        $prev = "CURRENT_TIMESTAMP - INTERVAL '" . ($days * 2) . " days'";
        $pair = function (callable $sqlFor) use ($cur, $prev): array {
            $now = (float)($this->db->fetchOne($sqlFor($cur, 'CURRENT_TIMESTAMP'))['v'] ?? 0);
            $before = (float)($this->db->fetchOne($sqlFor($prev, $cur))['v'] ?? 0);
            return ['value' => $now, 'previous' => $before];
        };
        return [
            'signups' => $pair(fn($from, $to) => "SELECT COUNT(*) AS v FROM users WHERE created_at >= {$from} AND created_at < {$to}"),
            'active' => $pair(fn($from, $to) => "SELECT COUNT(DISTINCT c.user_id) AS v FROM messages m JOIN conversations c ON c.id = m.conversation_id
                                                  WHERE m.role = 'user' AND m.created_at >= {$from} AND m.created_at < {$to}"),
            'messages' => $pair(fn($from, $to) => "SELECT COUNT(*) AS v FROM messages WHERE role = 'user' AND created_at >= {$from} AND created_at < {$to}"),
            'ai_cost' => $pair(fn($from, $to) => "SELECT COALESCE(SUM(cost_usd), 0) AS v FROM ai_usage WHERE created_at >= {$from} AND created_at < {$to}"),
        ];
    }

    /** Current recurring revenue from real (Dodo) subscriptions, plus test-plan counts. */
    public function revenue(): array {
        $mrr = 0.0;
        foreach ($this->db->fetchAll(
            'SELECT plan_status, billing_interval, COUNT(*) AS cnt FROM users WHERE ' . self::REAL_PAYING . ' GROUP BY 1, 2'
        ) as $row) {
            $interval = ($row['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
            $mrr += (AdminController::MONTHLY_PRICE_USD[$row['plan_status']][$interval] ?? 0) * (int)$row['cnt'];
        }
        return [
            'mrr' => $mrr,
            'real_subscribers' => (int)$this->db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE ' . self::REAL_PAYING)['c'],
            'test_subscribers' => (int)$this->db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE ' . self::TEST_PAYING)['c'],
            'total_users' => (int)$this->db->fetchOne('SELECT COUNT(*) AS c FROM users')['c'],
            'trials' => (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM users WHERE plan_status = 'trial'")['c'],
        ];
    }

    /**
     * One row per day for the last $days days (zero-filled): signups, active
     * users, user messages and AI cost.
     */
    public function daily(int $days): array {
        $start = 'CURRENT_DATE - ' . ($days - 1);
        return $this->db->fetchAll(
            "WITH d AS (SELECT generate_series({$start}, CURRENT_DATE, INTERVAL '1 day')::date AS day),
                  s AS (SELECT created_at::date AS day, COUNT(*) AS n FROM users WHERE created_at >= {$start} GROUP BY 1),
                  m AS (SELECT m.created_at::date AS day, COUNT(*) AS msgs, COUNT(DISTINCT c.user_id) AS active
                        FROM messages m JOIN conversations c ON c.id = m.conversation_id
                        WHERE m.role = 'user' AND m.created_at >= {$start} GROUP BY 1),
                  a AS (SELECT created_at::date AS day, SUM(cost_usd) AS cost FROM ai_usage WHERE created_at >= {$start} GROUP BY 1)
             SELECT d.day, COALESCE(s.n, 0) AS signups, COALESCE(m.active, 0) AS active,
                    COALESCE(m.msgs, 0) AS messages, COALESCE(a.cost, 0) AS ai_cost
             FROM d LEFT JOIN s USING (day) LEFT JOIN m USING (day) LEFT JOIN a USING (day)
             ORDER BY d.day"
        );
    }

    /** Signup funnel for users who registered in the last $days days. */
    public function funnel(int $days): array {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS registered,
                    COUNT(*) FILTER (WHERE u.onboarding_completed = 1) AS onboarded,
                    COUNT(*) FILTER (WHERE u.plan_status <> 'inactive' OR u.has_paid = 1) AS started,
                    COUNT(*) FILTER (WHERE EXISTS (SELECT 1 FROM conversations c JOIN messages m ON m.conversation_id = c.id
                                                   WHERE c.user_id = u.id AND m.role = 'user')) AS messaged,
                    COUNT(*) FILTER (WHERE u.has_paid = 1 AND u.dodo_subscription_id IS NOT NULL) AS paid
             FROM users u WHERE u.created_at >= CURRENT_TIMESTAMP - INTERVAL '{$days} days'"
        ) ?? [];
        return array_map('intval', $row);
    }

    public function languages(): array {
        return $this->db->fetchAll(
            "SELECT COALESCE(target_lang, 'en') AS lang, COUNT(*) AS cnt FROM users WHERE onboarding_completed = 1 GROUP BY 1 ORDER BY 2 DESC"
        );
    }

    /** Users per plan, with paid plans split into real (Dodo) and test. */
    public function plans(): array {
        return $this->db->fetchAll(
            "SELECT CASE WHEN has_paid = 1 AND dodo_subscription_id IS NULL AND plan_status IN ('starter','pro','active')
                         THEN plan_status || ':test' ELSE plan_status END AS plan, COUNT(*) AS cnt
             FROM users GROUP BY 1 ORDER BY 2 DESC"
        );
    }

    /** Things an admin should act on. */
    public function todos(): array {
        $ai = $this->db->fetchOne(
            "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE NOT ok) AS failed FROM ai_usage WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'"
        );
        return [
            'manual_cancellations' => $this->db->fetchAll(
                "SELECT id, email, plan_status, cancel_requested_at FROM users
                 WHERE cancel_requested_at IS NOT NULL AND cancel_method = 'manual' AND has_paid = 1 ORDER BY cancel_requested_at"
            ),
            'refunds' => $this->db->fetchAll(
                'SELECT id, email, plan_status, refund_requested_at FROM users WHERE refund_requested_at IS NOT NULL ORDER BY refund_requested_at'
            ),
            'ai_total_24h' => (int)($ai['total'] ?? 0),
            'ai_failed_24h' => (int)($ai['failed'] ?? 0),
            'admin_login_fails_24h' => (int)$this->db->fetchOne(
                "SELECT COUNT(*) AS c FROM login_attempts WHERE type = 'admin-login' AND attempted_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'"
            )['c'],
            'user_login_fails_24h' => (int)$this->db->fetchOne(
                "SELECT COUNT(*) AS c FROM login_attempts WHERE type = 'login' AND attempted_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'"
            )['c'],
        ];
    }

    public function recentSignups(int $limit = 6): array {
        return $this->db->fetchAll(
            'SELECT id, email, name, plan_status, target_lang, has_paid, dodo_subscription_id, created_at FROM users ORDER BY created_at DESC LIMIT ' . (int)$limit
        );
    }

    public function recentEvents(int $limit = 8): array {
        return $this->db->fetchAll(
            'SELECT ae.event_type, ae.provider, ae.plan, ae.created_at, u.email
             FROM activity_events ae LEFT JOIN users u ON u.id = ae.user_id
             ORDER BY ae.created_at DESC LIMIT ' . (int)$limit
        );
    }
}
