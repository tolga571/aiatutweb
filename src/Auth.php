<?php
namespace App\Src;

class Auth {
    private Database $db;
    public string $lastError = '';
    public function __construct(Database $db) {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    public function login(string $email, string $password): bool {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) {
            $this->lastError = __('auth.error_email_not_found');
            return false;
        }
        if (!password_verify($password, $user['password'])) {
            $this->lastError = __('auth.error_wrong_password');
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        
        $today = date('Y-m-d');
        $lastActivity = $user['last_activity_date'] ?? null;
        
        if ($lastActivity !== $today) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $streak = (int)($user['streak_count'] ?? 0);
            
            if ($lastActivity === $yesterday) {
                $streak++;
            } else {
                $streak = 1;
            }
            
            $this->db->execute(
                'UPDATE users SET streak_count = ?, last_activity_date = ? WHERE id = ?',
                [$streak, $today, $user['id']]
            );
        }
        
        return true;
    }

    public function register(string $email, string $password, string $name = ''): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $this->db->execute(
                'INSERT INTO users (email, password, name, profile_image) VALUES (?, ?, ?, ?)',
                [$email, $hash, $name, null]
            );
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function logout(): void {
        // Unset all session variables
        $_SESSION = [];
        // If there's a session cookie, delete it
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        // Finally destroy the session
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function userId(): ?int {
        return $this->isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    public function currentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$this->userId()]);
    }

    public function hasPaid(): bool {
        if (!$this->isLoggedIn()) return false;
        $user = $this->db->fetchOne('SELECT plan_status, has_paid FROM users WHERE id = ?', [$this->userId()]);
        return $user && ($user['plan_status'] === 'active' || $user['plan_status'] === 'trial' || $user['has_paid'] == 1);
    }

    public function getTrialMessagesSent(int $userId): int {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE c.user_id = ? AND m.role = 'user'",
            [$userId]
        );
        return $row ? (int)$row['cnt'] : 0;
    }

    public function hasCompletedOnboarding(): bool {
        if (!$this->isLoggedIn()) return false;
        $user = $this->db->fetchOne('SELECT onboarding_completed FROM users WHERE id = ?', [$this->userId()]);
        return $user && $user['onboarding_completed'] == 1;
    }

    public function saveOnboarding(int $userId, string $nativeLang, string $targetLang, string $cefrLevel, string $learningGoal, string $interestArea): void {
        $this->db->execute(
            'UPDATE users SET native_lang=?, target_lang=?, cefr_level=?, learning_goal=?, interest_area=?, onboarding_completed=1 WHERE id=?',
            [$nativeLang, $targetLang, $cefrLevel, $learningGoal, $interestArea, $userId]
        );
    }

    public function activatePlan(int $userId): void {
        $this->db->execute('UPDATE users SET plan_status=?, has_paid=1 WHERE id=?', ['active', $userId]);
    }

    public function addXp(int $userId, int $xp = 10): void {
        $this->db->execute('UPDATE users SET xp = xp + ? WHERE id = ?', [$xp, $userId]);
    }
}
?>
