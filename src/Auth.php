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
        session_regenerate_id(true);
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
            $this->lastError = ($e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate key'))
                ? __('auth.error_email_taken')
                : __('auth.registration_failed');
            return false;
        }
    }

    /**
     * True if the given IP has made $limit or more attempts of $type within
     * the last $windowSeconds. Used to throttle login/register abuse.
     */
    public function tooManyAttempts(string $ip, string $type, int $limit, int $windowSeconds): bool {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND type = ? AND attempted_at > CURRENT_TIMESTAMP - INTERVAL '" . (int)$windowSeconds . " seconds'",
            [$ip, $type]
        );
        return $row && (int)$row['cnt'] >= $limit;
    }

    public function recordAttempt(string $ip, string $type): void {
        $this->db->execute('INSERT INTO login_attempts (ip, type) VALUES (?, ?)', [$ip, $type]);
        // Opportunistic cleanup so the table doesn't grow unbounded.
        $this->db->execute("DELETE FROM login_attempts WHERE attempted_at < CURRENT_TIMESTAMP - INTERVAL '1 day'");
    }

    public function clearAttempts(string $ip, string $type): void {
        $this->db->execute('DELETE FROM login_attempts WHERE ip = ? AND type = ?', [$ip, $type]);
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

    /**
     * Creates a password reset token for the given user, storing only its
     * hash (the plaintext token lives solely in the emailed link) with a
     * 1 hour expiry. Returns the plaintext token to embed in the link.
     */
    public function createPasswordResetToken(int $userId): string {
        $token = bin2hex(random_bytes(32));
        $this->db->execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '1 hour')",
            [$userId, hash('sha256', $token)]
        );
        return $token;
    }

    /** Looks up the user for an unexpired, unused reset token without consuming it (for rendering the reset form). */
    public function findValidResetUserId(string $token): ?int {
        $row = $this->db->fetchOne(
            "SELECT user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP",
            [hash('sha256', $token)]
        );
        return $row ? (int)$row['user_id'] : null;
    }

    /** Validates the token again, sets the new password, and marks the token used — all in one step so it can't be replayed. */
    public function completePasswordReset(string $token, string $newPassword): bool {
        $tokenHash = hash('sha256', $token);
        $row = $this->db->fetchOne(
            "SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP",
            [$tokenHash]
        );
        if (!$row) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $row['user_id']]);
        $this->db->execute("UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = ?", [$row['id']]);
        // Any other outstanding reset links for this user should die with it.
        $this->db->execute("UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL", [$row['user_id']]);
        return true;
    }

    /** Creates an email verification token for the given user (24 hour expiry). Returns the plaintext token to embed in the link. */
    public function createEmailVerificationToken(int $userId): string {
        $token = bin2hex(random_bytes(32));
        $this->db->execute(
            "INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '24 hours')",
            [$userId, hash('sha256', $token)]
        );
        return $token;
    }

    /** Validates the token, marks the user's email verified, and marks the token used. */
    public function verifyEmailToken(string $token): bool {
        $tokenHash = hash('sha256', $token);
        $row = $this->db->fetchOne(
            "SELECT id, user_id FROM email_verifications WHERE token_hash = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP",
            [$tokenHash]
        );
        if (!$row) {
            return false;
        }
        $this->db->execute('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = ?', [$row['user_id']]);
        $this->db->execute("UPDATE email_verifications SET used_at = CURRENT_TIMESTAMP WHERE id = ?", [$row['id']]);
        return true;
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
