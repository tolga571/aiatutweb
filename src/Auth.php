<?php
namespace App\Src;

class Auth {
    private Database $db;
    public string $lastError = '';
    /** $startSession is false for the mobile API, which uses bearer tokens instead of a session. */
    public function __construct(Database $db, bool $startSession = true) {
        $this->db = $db;
        if ($startSession && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    public function login(string $email, string $password): bool {
        $user = $this->verifyCredentials($email, $password);
        if (!$user) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $this->recordActivity($user);
        return true;
    }

    /**
     * The user row for a correct email + password of an account that isn't
     * suspended, else null with $lastError set. Shared by the web login and
     * the mobile API.
     */
    public function verifyCredentials(string $email, string $password): ?array {
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        // One message for "no such email" and "wrong password" — separate
        // ones let anyone check which emails have an account here. The
        // dummy hash keeps the response time the same for both.
        if (!password_verify($password, $user['password'] ?? '$2y$12$5GzKbBJp.Lli2waBJFd86.JCxXjXQ1mRvuN2dmlrtQP1X0wZ/md0e') || !$user) {
            $this->lastError = __('auth.invalid_credentials');
            return null;
        }
        if (!empty($user['suspended_at'])) {
            $this->lastError = __('auth.account_suspended');
            return null;
        }
        if (empty($user['email_verified_at']) && empty($user['google_id'])) {
            $this->lastError = __('auth.error_unverified_email');
            return null;
        }
        return $user;
    }

    /** Daily streak bookkeeping on sign-in. */
    public function recordActivity(array $user): void {
        $today = date('Y-m-d');
        $lastActivity = $user['last_activity_date'] ?? null;
        if ($lastActivity === $today) {
            return;
        }
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $streak = $lastActivity === $yesterday ? (int)($user['streak_count'] ?? 0) + 1 : 1;
        $this->db->execute(
            'UPDATE users SET streak_count = ?, last_activity_date = ? WHERE id = ?',
            [$streak, $today, $user['id']]
        );
    }

    /**
     * Verifies a Google ID token with Google and returns its claims when it
     * was issued for one of $audiences (our OAuth client IDs), by Google, for
     * a verified email; null otherwise. A token for another app, or for an
     * unverified address, could otherwise be used to take over the account
     * registered with that email.
     */
    public static function verifyGoogleIdToken(string $idToken, array $audiences): ?array {
        $audiences = array_values(array_filter($audiences));
        if ($idToken === '' || !$audiences) {
            return null;
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get('https://oauth2.googleapis.com/tokeninfo', ['query' => ['id_token' => $idToken]]);
            $claims = json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return null;
        }
        if (is_array($claims)
            && in_array($claims['aud'] ?? '', $audiences, true)
            && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && in_array($claims['email_verified'] ?? false, [true, 'true'], true)
            && trim($claims['email'] ?? '') !== '') {
            return $claims;
        }
        return null;
    }

    /**
     * The account for verified Google claims: found by Google ID, else by
     * email (and linked), else created. Returns [user row, created?].
     */
    /** $allowCreate = false: sign existing users in but don't create a new account (returns [null, false]). */
    public function findOrCreateGoogleUser(array $claims, bool $allowCreate = true): array {
        $googleId = (string)($claims['sub'] ?? '');
        $email = trim((string)($claims['email'] ?? ''));
        $name = trim((string)($claims['name'] ?? ''));
        $picture = trim((string)($claims['picture'] ?? ''));
        $created = false;
        $user = $this->db->fetchOne('SELECT * FROM users WHERE google_id = ?', [$googleId]);
        if (!$user) {
            $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
            if ($user) {
                $this->db->execute(
                    'UPDATE users SET google_id = ?, profile_image = COALESCE(profile_image, ?) WHERE id = ?',
                    [$googleId, $picture ?: null, $user['id']]
                );
            } elseif (!$allowCreate) {
                return [null, false];
            } else {
                $this->db->execute(
                    'INSERT INTO users (email, password, name, google_id, profile_image) VALUES (?, ?, ?, ?, ?)',
                    [$email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $name, $googleId, $picture ?: null]
                );
                $created = true;
            }
            $user = $this->db->fetchOne('SELECT * FROM users WHERE google_id = ?', [$googleId]);
        } elseif ($picture !== '' && $user['profile_image'] !== $picture) {
            $this->db->execute('UPDATE users SET profile_image = ? WHERE id = ?', [$picture, $user['id']]);
        }
        return [$user, $created];
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
        // Any other outstanding reset links for this user should die with it,
        // and so should the app sign-ins made with the old password.
        $this->db->execute("UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL", [$row['user_id']]);
        $this->db->execute('DELETE FROM api_tokens WHERE user_id = ?', [$row['user_id']]);
        $this->endWebSessions((int)$row['user_id']);
        return true;
    }

    /**
     * Signs the user out of every browser (e.g. after a password reset, so a
     * stolen session dies with the old password). Sessions are serialized
     * PHP data in the sessions table; "user_id|i:N;" is matched only at the
     * start or after a previous value, so "admin_user_id" can't match.
     */
    public function endWebSessions(int $userId): void {
        // The id may have been stored as an int or as a numeric string.
        foreach (['user_id|i:' . $userId . ';', 'user_id|s:' . strlen((string)$userId) . ':"' . $userId . '";'] as $needle) {
            $this->db->execute(
                "DELETE FROM sessions WHERE data LIKE ? OR data LIKE ? OR data LIKE ?",
                [$needle . '%', '%;' . $needle . '%', '%}' . $needle . '%']
            );
        }
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

    public const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
    public const LEARNING_GOALS = ['conversation', 'travel', 'work', 'exam'];
    public const INTEREST_AREAS = ['general', 'tech', 'movies', 'sports', 'business'];

    /** $value if it's one of $allowed, else $default — profile fields only ever hold known values. */
    public static function pick(?string $value, array $allowed, string $default): string {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function saveOnboarding(int $userId, string $nativeLang, string $targetLang, string $cefrLevel, string $learningGoal, string $interestArea): void {
        $langs = Language::supportedLangs();
        $nativeLang = self::pick($nativeLang, $langs, 'en');
        $targetLang = self::pick($targetLang, $langs, 'en');
        $cefrLevel = self::pick($cefrLevel, self::CEFR_LEVELS, 'A1');
        $learningGoal = self::pick($learningGoal, self::LEARNING_GOALS, 'conversation');
        $interestArea = self::pick($interestArea, self::INTEREST_AREAS, 'general');
        // The site then speaks the user's native language (when the interface
        // is available in it); they can still switch it later.
        $uiLang = Language::isUsable($nativeLang, 'ui') ? $nativeLang : Language::DEFAULT;
        $this->db->execute(
            'UPDATE users SET native_lang=?, target_lang=?, cefr_level=?, learning_goal=?, interest_area=?, ui_lang=?, onboarding_completed=1 WHERE id=?',
            [$nativeLang, $targetLang, $cefrLevel, $learningGoal, $interestArea, $uiLang, $userId]
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
