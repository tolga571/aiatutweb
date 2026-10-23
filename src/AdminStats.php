<?php
namespace App\Src;

/**
 * Numbers behind the admin dashboard.
 *
 * Revenue rule: only subscriptions that are active in the LIVE Dodo account
 * (mirrored into dodo_live_subscriptions by DodoSync) are real money. A paid
 * flag or a Dodo subscription id in users alone proves nothing: sandbox
 * checkouts, test-mode subscriptions and hand-granted plans all set those.
 * Such rows are "test"; ones that carry a Dodo id Dodo doesn't know as active
 * are also reported as "unverified" so the mismatch is visible.
 */
class AdminStats {
    /** SQL condition for a user paying real money. */
    public const LIVE_DODO_IDS = "SELECT subscription_id FROM dodo_live_subscriptions WHERE status = 'active'";
    public const REAL_PAYING = "has_paid = 1 AND dodo_subscription_id IN (" . self::LIVE_DODO_IDS . ")";
    /** SQL condition for a paid plan that isn't real money (sandbox, test mode, manual, stale). */
    public const TEST_PAYING = "has_paid = 1 AND (dodo_subscription_id IS NULL OR dodo_subscription_id NOT IN (" . self::LIVE_DODO_IDS . "))";
    /** Paid plan pointing at a Dodo subscription that isn't active in the live account. */
    public const UNVERIFIED_PAYING = "has_paid = 1 AND dodo_subscription_id IS NOT NULL AND dodo_subscription_id NOT IN (" . self::LIVE_DODO_IDS . ")";

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

    /**
     * Current recurring revenue: Dodo's own amounts for the subscriptions that
     * are active in the live account, normalised to a month. Plus test-plan counts.
     */
    public function revenue(): array {
        $months = ['Day' => 1 / 30, 'Week' => 7 / 30, 'Month' => 1, 'Year' => 12];
        $mrr = 0.0;
        foreach ($this->db->fetchAll(
            "SELECT amount_cents, interval_unit, interval_count FROM dodo_live_subscriptions WHERE status = 'active'"
        ) as $row) {
            $period = ($months[$row['interval_unit']] ?? 1) * max(1, (int)$row['interval_count']);
            $mrr += (int)$row['amount_cents'] / 100 / $period;
        }
        return [
            'mrr' => $mrr,
            'real_subscribers' => (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM dodo_live_subscriptions WHERE status = 'active'")['c'],
            'unverified_subscribers' => (int)$this->db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE ' . self::UNVERIFIED_PAYING)['c'],
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
                    COUNT(*) FILTER (WHERE " . self::REAL_PAYING . ") AS paid
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
            "SELECT CASE WHEN " . self::TEST_PAYING . " AND plan_status IN ('starter','pro','active')
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
                'SELECT id, email, plan_status, cancel_requested_at FROM users WHERE ' . AdminRevenue::OPEN_MANUAL_CANCEL . ' ORDER BY cancel_requested_at'
            ),
            'refunds' => $this->db->fetchAll(
                'SELECT id, email, plan_status, refund_requested_at FROM users WHERE ' . AdminRevenue::OPEN_REFUND . ' ORDER BY refund_requested_at'
            ),
            'open_reports' => (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM ai_reports WHERE status = 'open'")['c'],
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
