<?php
namespace App\Src;

class TokenManager {
    private Database $db;
    public function __construct(Database $db) {
        $this->db = $db;
    }

    private function ensureUserRow(int $userId): void {
        $row = $this->db->fetchOne('SELECT id FROM token_usage WHERE user_id = ?', [$userId]);
        if (!$row) {
            $this->db->execute('INSERT INTO token_usage (user_id, used_this_month, last_reset_month, bonus_limit) VALUES (?, 0, ?, 0)', [$userId, date('Y-m')]);
        }
    }

    private function resetIfNewMonth(int $userId): void {
        $row = $this->db->fetchOne('SELECT last_reset_month FROM token_usage WHERE user_id = ?', [$userId]);
        $currentMonth = date('Y-m');
        if ($row && $row['last_reset_month'] !== $currentMonth) {
            $this->db->execute('UPDATE token_usage SET used_this_month = 0, bonus_limit = 0, last_reset_month = ? WHERE user_id = ?', [$currentMonth, $userId]);
        }
    }

    public function getBaseLimit(string $planStatus): int {
        switch ($planStatus) {
            case 'trial': return 5;
            case 'starter': return 50;
            case 'pro': return 500;
            case 'active': return 1500;
            default: return 0;
        }
    }

    /**
     * Relative tier of a plan, used to tell an upgrade from a downgrade.
     * 'active' is the internal plan_status value for the Premium plan.
     */
    public static function planRank(string $planStatus): int {
        switch ($planStatus) {
            case 'starter': return 1;
            case 'pro': return 2;
            case 'active': return 3;
            default: return 0;
        }
    }

    public function getRemaining(int $userId): int {
        $this->ensureUserRow($userId);
        $this->resetIfNewMonth($userId);

        $usage = $this->db->fetchOne('SELECT used_this_month, bonus_limit FROM token_usage WHERE user_id = ?', [$userId]);
        $user = $this->db->fetchOne('SELECT plan_status, has_paid FROM users WHERE id = ?', [$userId]);

        $used = $usage ? (int)$usage['used_this_month'] : 0;
        $bonus = $usage ? (int)$usage['bonus_limit'] : 0;

        $planStatus = $user ? $user['plan_status'] : 'inactive';
        $baseLimit = $this->getBaseLimit($planStatus);

        return max(0, ($baseLimit + $bonus) - $used);
    }

    public function addUsage(int $userId, int $tokens = 1): void {
        $this->ensureUserRow($userId);
        $this->resetIfNewMonth($userId);
        $this->db->execute('UPDATE token_usage SET used_this_month = used_this_month + ? WHERE user_id = ?', [$tokens, $userId]);
    }
}
?>
