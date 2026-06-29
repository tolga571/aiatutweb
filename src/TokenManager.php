<?php
namespace App\Src;

class TokenManager {
    private Database $db;
    private int $dailyLimit;

    public function __construct(Database $db, int $dailyLimit) {
        $this->db = $db;
        $this->dailyLimit = $dailyLimit;
    }

    private function ensureUserRow(int $userId): void {
        $row = $this->db->fetchOne('SELECT id FROM token_usage WHERE user_id = ?', [$userId]);
        if (!$row) {
            $this->db->execute('INSERT INTO token_usage (user_id, used_today, last_reset) VALUES (?, 0, DATE("now"))', [$userId]);
        }
    }

    private function resetIfNewDay(int $userId): void {
        $row = $this->db->fetchOne('SELECT last_reset FROM token_usage WHERE user_id = ?', [$userId]);
        if ($row && $row['last_reset'] !== date('Y-m-d')) {
            $this->db->execute('UPDATE token_usage SET used_today = 0, last_reset = DATE("now") WHERE user_id = ?', [$userId]);
        }
    }

    public function getRemaining(int $userId): int {
        $this->ensureUserRow($userId);
        $this->resetIfNewDay($userId);
        $row = $this->db->fetchOne('SELECT used_today FROM token_usage WHERE user_id = ?', [$userId]);
        $used = $row ? (int)$row['used_today'] : 0;
        return max(0, $this->dailyLimit - $used);
    }

    public function addUsage(int $userId, int $tokens = 1): void {
        $this->ensureUserRow($userId);
        $this->resetIfNewDay($userId);
        $this->db->execute('UPDATE token_usage SET used_today = used_today + ? WHERE user_id = ?', [$tokens, $userId]);
    }
}
?>
