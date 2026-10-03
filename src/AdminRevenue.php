<?php
namespace App\Src;

/**
 * Revenue & subscriptions page. Real money = Dodo subscriptions only (see
 * AdminStats::REAL_PAYING); Paddle sandbox plans are listed as "test".
 */
class AdminRevenue {
    /** Open refund request: asked for and not closed since. */
    public const OPEN_REFUND = "refund_requested_at IS NOT NULL AND (refund_handled_at IS NULL OR refund_handled_at < refund_requested_at)";
    /** Open manual cancellation: no API on file, so support has to cancel it at the provider. */
    public const OPEN_MANUAL_CANCEL = "cancel_requested_at IS NOT NULL AND cancel_method = 'manual' AND has_paid = 1
                                       AND (cancel_handled_at IS NULL OR cancel_handled_at < cancel_requested_at)";
    public const PER_PAGE = 50;

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /** Monthly price of one subscription row, USD. */
    private static function monthly(array $row): float {
        $interval = ($row['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
        return (float)(AdminController::MONTHLY_PRICE_USD[$row['plan_status']][$interval] ?? 0);
    }

    /** Upserts today's MRR row. Cheap; called on admin page loads. */
    public function snapshot(): void {
        $rev = (new AdminStats($this->db))->revenue();
        $this->db->execute(
            'INSERT INTO revenue_snapshots (day, mrr, real_subscribers, test_subscribers) VALUES (CURRENT_DATE, ?, ?, ?)
             ON CONFLICT (day) DO UPDATE SET mrr = EXCLUDED.mrr, real_subscribers = EXCLUDED.real_subscribers,
                                             test_subscribers = EXCLUDED.test_subscribers, updated_at = CURRENT_TIMESTAMP',
            [round($rev['mrr'], 2), $rev['real_subscribers'], $rev['test_subscribers']]
        );
    }

    public function kpis(): array {
        $rev = (new AdminStats($this->db))->revenue();
        $moves = $this->db->fetchOne(
            "SELECT COUNT(*) FILTER (WHERE event_type = 'subscription_started') AS started,
                    COUNT(*) FILTER (WHERE event_type = 'subscription_canceled') AS canceled
             FROM activity_events WHERE provider = 'dodo' AND created_at >= CURRENT_TIMESTAMP - INTERVAL '30 days'"
        ) ?? [];
        $canceled = (int)($moves['canceled'] ?? 0);
        $base = $rev['real_subscribers'] + $canceled;
        return $rev + [
            'arr' => $rev['mrr'] * 12,
            'arpu' => $rev['real_subscribers'] ? $rev['mrr'] / $rev['real_subscribers'] : 0,
            'new_30d' => (int)($moves['started'] ?? 0),
            'canceled_30d' => $canceled,
            'churn_30d' => $base ? $canceled / $base * 100 : 0,
            'cancel_scheduled' => (int)$this->db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE ' . AdminStats::REAL_PAYING . ' AND cancel_requested_at IS NOT NULL')['c'],
        ];
    }

    /** Daily MRR snapshots for the last $days days. */
    public function history(int $days = 90): array {
        return $this->db->fetchAll(
            'SELECT day, mrr, real_subscribers, test_subscribers FROM revenue_snapshots WHERE day >= CURRENT_DATE - ' . (int)$days . ' ORDER BY day'
        );
    }

    /** New / cancelled / up- / downgraded Dodo subscriptions per month, last $months months (zero-filled). */
    public function monthlyMovements(int $months = 6): array {
        return $this->db->fetchAll(
            "WITH m AS (SELECT generate_series(date_trunc('month', CURRENT_DATE) - INTERVAL '" . ((int)$months - 1) . " months',
                                               date_trunc('month', CURRENT_DATE), INTERVAL '1 month')::date AS month)
             SELECT m.month,
                    COUNT(e.id) FILTER (WHERE e.event_type = 'subscription_started') AS started,
                    COUNT(e.id) FILTER (WHERE e.event_type = 'subscription_canceled') AS canceled,
                    COUNT(e.id) FILTER (WHERE e.event_type = 'subscription_upgraded') AS upgraded,
                    COUNT(e.id) FILTER (WHERE e.event_type IN ('subscription_downgraded', 'downgrade_scheduled')) AS downgraded
             FROM m LEFT JOIN activity_events e
               ON e.provider = 'dodo' AND date_trunc('month', e.created_at)::date = m.month
             GROUP BY m.month ORDER BY m.month"
        );
    }

    /** Real subscribers per plan and interval, with their MRR share. */
    public function planMix(): array {
        $rows = $this->db->fetchAll(
            'SELECT plan_status, billing_interval, COUNT(*) AS cnt FROM users WHERE ' . AdminStats::REAL_PAYING . ' GROUP BY 1, 2 ORDER BY 1, 2'
        );
        foreach ($rows as &$r) {
            $r['mrr'] = self::monthly($r) * (int)$r['cnt'];
        }
        return $rows;
    }

    /** Open refund requests and manual cancellations, oldest first. */
    public function queue(): array {
        $cols = 'id, email, name, plan_status, billing_interval, dodo_subscription_id, paddle_subscription_id, fastspring_subscription_id, has_paid';
        return [
            'refunds' => $this->db->fetchAll("SELECT {$cols}, refund_requested_at AS requested_at FROM users WHERE " . self::OPEN_REFUND . ' ORDER BY refund_requested_at'),
            'cancellations' => $this->db->fetchAll("SELECT {$cols}, cancel_requested_at AS requested_at FROM users WHERE " . self::OPEN_MANUAL_CANCEL . ' ORDER BY cancel_requested_at'),
        ];
    }

    /** Recently closed queue items, from the audit trail. */
    public function recentlyHandled(int $limit = 8): array {
        return $this->db->fetchAll(
            "SELECT a.action, a.detail, a.admin_email, a.performed_at, a.target_user_id, u.email
             FROM admin_audit a LEFT JOIN users u ON u.id = a.target_user_id
             WHERE a.action IN ('refund_handled', 'cancellation_handled')
             ORDER BY a.performed_at DESC LIMIT " . (int)$limit
        );
    }

    /** Real subscriptions renewing in the next $days days. */
    public function upcomingRenewals(int $days = 7): array {
        $rows = $this->db->fetchAll(
            'SELECT id, email, plan_status, billing_interval, next_billed_at, cancel_requested_at FROM users
             WHERE ' . AdminStats::REAL_PAYING . " AND next_billed_at BETWEEN CURRENT_TIMESTAMP AND CURRENT_TIMESTAMP + INTERVAL '" . (int)$days . " days'
             ORDER BY next_billed_at"
        );
        foreach ($rows as &$r) {
            $r['amount'] = ($r['billing_interval'] ?? 'month') === 'year'
                ? self::monthly($r) * 12
                : self::monthly($r);
        }
        return $rows;
    }

    /**
     * Paid-plan users. $f keys: type (real|test|all), status
     * (active|cancel|change), q.
     */
    public function subscriptions(array $f, int $page): array {
        $where = ['u.has_paid = 1'];
        $params = [];
        if (($f['type'] ?? 'real') === 'real') {
            $where[] = 'u.dodo_subscription_id IS NOT NULL';
        } elseif (($f['type'] ?? '') === 'test') {
            $where[] = 'u.dodo_subscription_id IS NULL';
        }
        $where[] = match ($f['status'] ?? '') {
            'active' => 'u.cancel_requested_at IS NULL AND u.pending_plan_change IS NULL',
            'cancel' => 'u.cancel_requested_at IS NOT NULL',
            'change' => 'u.pending_plan_change IS NOT NULL',
            default => 'TRUE',
        };
        if (($f['q'] ?? '') !== '') {
            $where[] = '(u.email ILIKE ? OR u.dodo_subscription_id = ? OR u.paddle_subscription_id = ?)';
            array_push($params, '%' . $f['q'] . '%', $f['q'], $f['q']);
        }
        $whereSql = implode(' AND ', $where);
        $total = (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM users u WHERE {$whereSql}", $params)['c'];
        $offset = (max(1, $page) - 1) * self::PER_PAGE;
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.email, u.plan_status, u.billing_interval, u.dodo_subscription_id, u.paddle_subscription_id,
                    u.fastspring_subscription_id, u.next_billed_at, u.cancel_requested_at, u.cancel_method,
                    u.pending_plan_change, u.refund_requested_at, u.created_at
             FROM users u WHERE {$whereSql} ORDER BY u.next_billed_at ASC NULLS LAST, u.id DESC
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );
        foreach ($rows as &$r) {
            $r['monthly'] = self::monthly($r);
        }
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int)ceil($total / self::PER_PAGE))];
    }

    /** Closes a queue item. $kind: refund|cancel. Returns the flash line. */
    public function resolve(int $userId, string $kind): string {
        if ($kind === 'refund') {
            $n = $this->db->execute('UPDATE users SET refund_handled_at = CURRENT_TIMESTAMP WHERE id = ? AND ' . self::OPEN_REFUND, [$userId]);
        } elseif ($kind === 'cancel') {
            $n = $this->db->execute('UPDATE users SET cancel_handled_at = CURRENT_TIMESTAMP WHERE id = ? AND ' . self::OPEN_MANUAL_CANCEL, [$userId]);
        } else {
            throw new \InvalidArgumentException(t('admin.err_unknown_action'));
        }
        if ($n === 0) {
            throw new \InvalidArgumentException(t('admin.err_request_closed'));
        }
        return $kind === 'refund' ? t('admin.refund_closed') : t('admin.cancel_closed');
    }
}
