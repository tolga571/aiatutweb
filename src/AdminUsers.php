<?php
namespace App\Src;

/**
 * Queries and actions behind the admin user list and user detail page.
 * Every action here is called from AdminController, which checks the
 * admin's role and CSRF and writes the audit row.
 */
class AdminUsers {
    public const PLANS = ['inactive', 'trial', 'starter', 'pro', 'active'];
    public const PAID_PLANS = ['starter', 'pro', 'active'];
    public const PER_PAGE = 50;

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Filtered, paginated user list. $f keys: q, plan, pay (real|test|free),
     * lang, status (suspended|unverified), sort (new|old|active|messages).
     */
    public function search(array $f, int $page): array {
        $where = [];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(u.email ILIKE ? OR u.name ILIKE ? OR CAST(u.id AS TEXT) = ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%', $f['q']);
        }
        if (in_array($f['plan'] ?? '', self::PLANS, true)) {
            $where[] = 'u.plan_status = ?';
            $params[] = $f['plan'];
        }
        $where[] = match ($f['pay'] ?? '') {
            'real' => AdminStats::REAL_PAYING,
            'test' => AdminStats::TEST_PAYING,
            'free' => 'u.has_paid = 0',
            default => 'TRUE',
        };
        if (($f['lang'] ?? '') !== '' && in_array($f['lang'], Language::supportedLangs(), true)) {
            $where[] = 'u.target_lang = ?';
            $params[] = $f['lang'];
        }
        $where[] = match ($f['status'] ?? '') {
            'suspended' => 'u.suspended_at IS NOT NULL',
            'unverified' => 'u.email_verified_at IS NULL',
            default => 'TRUE',
        };
        $whereSql = implode(' AND ', $where);
        $msgCount = "(SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.user_id = u.id AND m.role = 'user')";
        $order = match ($f['sort'] ?? 'new') {
            'old' => 'u.created_at ASC',
            'active' => 'u.last_activity_date DESC NULLS LAST, u.id DESC',
            'messages' => 'message_count DESC, u.id DESC',
            default => 'u.created_at DESC, u.id DESC',
        };
        $total = (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM users u WHERE {$whereSql}", $params)['c'];
        $offset = (max(1, $page) - 1) * self::PER_PAGE;
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.email, u.name, u.plan_status, u.has_paid, u.dodo_subscription_id, u.billing_interval,
                    u.target_lang, u.native_lang, u.created_at, u.last_activity_date, u.suspended_at, u.email_verified_at,
                    {$msgCount} AS message_count
             FROM users u WHERE {$whereSql} ORDER BY {$order} LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int)ceil($total / self::PER_PAGE))];
    }

    public function find(int $id): ?array {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Everything the detail page shows besides the user row itself. */
    public function details(int $id): array {
        $tm = new TokenManager($this->db);
        $remaining = $tm->getRemaining($id); // also creates/resets the month's row
        $usage = $this->db->fetchOne('SELECT used_this_month, bonus_limit FROM token_usage WHERE user_id = ?', [$id]) ?? [];
        $one = fn(string $sql) => $this->db->fetchOne($sql, [$id]) ?? [];
        return [
            'quota' => [
                'remaining' => $remaining,
                'used' => (int)($usage['used_this_month'] ?? 0),
                'bonus' => (int)($usage['bonus_limit'] ?? 0),
            ],
            'ai' => $one("SELECT COALESCE(SUM(cost_usd) FILTER (WHERE created_at >= date_trunc('month', CURRENT_TIMESTAMP)), 0) AS month_cost,
                                 COALESCE(SUM(cost_usd), 0) AS total_cost, COUNT(*) AS requests,
                                 COUNT(*) FILTER (WHERE NOT ok) AS failed
                          FROM ai_usage WHERE user_id = ?"),
            'counts' => $one("SELECT
                    (SELECT COUNT(*) FROM conversations WHERE user_id = u.id) AS conversations,
                    (SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.user_id = u.id AND m.role = 'user') AS messages,
                    (SELECT COUNT(*) FROM vocabulary_words WHERE user_id = u.id) AS words,
                    (SELECT COUNT(*) FROM user_flashcards WHERE user_id = u.id AND status = 'mastered') AS mastered,
                    (SELECT COUNT(*) FROM user_mistakes WHERE user_id = u.id AND learned_at IS NULL) AS mistakes_open,
                    (SELECT COUNT(*) FROM user_mistakes WHERE user_id = u.id AND learned_at IS NOT NULL) AS mistakes_learned
                FROM users u WHERE u.id = ?"),
            'conversations' => $this->db->fetchAll(
                "SELECT c.id, c.topic_id, c.created_at, c.updated_at,
                        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.role = 'user') AS user_messages,
                        (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id LIMIT 1) AS first_message
                 FROM conversations c WHERE c.user_id = ? ORDER BY c.updated_at DESC LIMIT 10",
                [$id]
            ),
            'timeline' => $this->db->fetchAll(
                "SELECT * FROM (
                    SELECT 'event' AS kind, event_type AS action, COALESCE(provider, '') || CASE WHEN plan IS NOT NULL THEN ' · ' || plan ELSE '' END AS detail,
                           NULL AS admin_email, created_at AS at
                    FROM activity_events WHERE user_id = ?
                    UNION ALL
                    SELECT 'admin', action, detail, admin_email, performed_at FROM admin_audit WHERE target_user_id = ?
                 ) t ORDER BY at DESC LIMIT 30",
                [$id, $id]
            ),
        ];
    }

    // ── Actions. Each returns a short result line for the flash message. ──

    public function setPlan(array $user, string $plan): string {
        if (!in_array($plan, self::PLANS, true)) {
            throw new \InvalidArgumentException(t('admin.invalid_plan'));
        }
        if (!empty($user['dodo_subscription_id']) && (int)$user['has_paid'] === 1) {
            // A real subscription is billed by Dodo; changing it here would
            // only desync the app from what the customer pays for.
            throw new \InvalidArgumentException(t('admin.has_real_sub'));
        }
        $paid = in_array($plan, self::PAID_PLANS, true) ? 1 : 0;
        $this->db->execute('UPDATE users SET plan_status = ?, has_paid = ? WHERE id = ?', [$plan, $paid, $user['id']]);
        return t('admin.plan_updated', ['plan' => $plan]) . ($paid ? ' ' . t('admin.granted_by_hand') : '');
    }

    public function addBonus(int $userId, int $amount): string {
        if ($amount < 1 || $amount > 10000) {
            throw new \InvalidArgumentException(t('admin.bonus_range'));
        }
        (new TokenManager($this->db))->getRemaining($userId); // make sure this month's row exists
        $this->db->execute('UPDATE token_usage SET bonus_limit = bonus_limit + ? WHERE user_id = ?', [$amount, $userId]);
        return t('admin.bonus_added', ['n' => $amount]);
    }

    public function resetUsage(int $userId): string {
        (new TokenManager($this->db))->getRemaining($userId);
        $this->db->execute('UPDATE token_usage SET used_this_month = 0 WHERE user_id = ?', [$userId]);
        return t('admin.usage_reset_done');
    }

    public function verifyEmail(int $userId): string {
        $this->db->execute('UPDATE users SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = ?', [$userId]);
        return t('admin.email_marked_verified');
    }

    public function setSuspended(int $userId, bool $suspend): string {
        $this->db->execute('UPDATE users SET suspended_at = ' . ($suspend ? 'CURRENT_TIMESTAMP' : 'NULL') . ' WHERE id = ?', [$userId]);
        if ($suspend) {
            // End any live session right away instead of at its next request.
            // (index.php also logs a suspended user out on their next request.)
            $this->db->execute(
                'DELETE FROM sessions WHERE data LIKE ? OR data LIKE ?',
                ['%user_id|i:' . $userId . ';%', '%user_id|s:' . strlen((string)$userId) . ':"' . $userId . '";%']
            );
        }
        return $suspend ? t('admin.account_suspended_done') : t('admin.account_reopened');
    }
}
