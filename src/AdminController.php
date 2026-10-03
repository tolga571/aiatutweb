<?php
namespace App\Src;

class AdminController {
    /**
     * List price per plan as a monthly amount, USD (yearly prices spread
     * over 12 months). Must track the prices on the pricing page
     * (lang/*.php pricing.*_monthly/_yearly) and the live Dodo catalog.
     */
    public const MONTHLY_PRICE_USD = [
        'starter' => ['month' => 15,  'year' => 150 / 12],
        'pro'     => ['month' => 50,  'year' => 500 / 12],
        'active'  => ['month' => 150, 'year' => 1500 / 12],
    ];

    /** Admin sessions end after this long without a request. */
    private const IDLE_TIMEOUT = 2 * 3600;

    private Database $db;
    private \PDO $pdo;
    private array $config;

    public function __construct(Database $db) {
        $this->db  = $db;
        $this->pdo = $db->getPdo();
        // Load config for admin controller (used in dashboard)
        $this->config = require __DIR__ . '/../config.php';
        // Ensure a session is started for admin auth & CSRF
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** Ensure the current user is an admin */
    private function requireAdmin(): void {
        // The admin panel has its own language (default Turkish), separate
        // from the site's interface language.
        Language::load(self::adminLang());
        $adminId = $_SESSION['admin_id'] ?? null;
        if (!$adminId) {
            header('Location: ?page=admin-login');
            exit;
        }
        // Admin access shares the site's 90-day session cookie, so it gets
        // its own idle timeout on top.
        if (time() - (int)($_SESSION['admin_last_seen'] ?? 0) > self::IDLE_TIMEOUT) {
            unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_last_seen']);
            $_SESSION['admin_login_error'] = t('admin.err_session_expired');
            header('Location: ?page=admin-login');
            exit;
        }
        $_SESSION['admin_last_seen'] = time();
        // optional sanity check that admin still exists
        $admin = $this->db->fetchOne('SELECT * FROM admins WHERE id = ?', [$adminId]);
        if (!$admin) {
            // stale session – clear and redirect
            unset($_SESSION['admin_id']);
            header('Location: ?page=admin-login');
            exit;
        }
        // Re-read on every request (cheap — this query already ran above)
        // rather than trusting a role cached at login time, so a role
        // change takes effect on the admin's very next click.
        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
        $this->currentAdmin = $admin;
    }

    public const ADMIN_LANG_COOKIE = 'jl_admin_lang';
    public const ADMIN_DEFAULT_LANG = 'tr';

    /** The admin panel's interface language: its own cookie, else Turkish. */
    public static function adminLang(): string {
        $c = (string)($_COOKIE[self::ADMIN_LANG_COOKIE] ?? '');
        return ($c !== '' && Language::isUsable($c, 'ui')) ? $c : self::ADMIN_DEFAULT_LANG;
    }

    /** Switches the admin panel language (?page=admin-lang&lang=xx) and goes back. */
    public function setAdminLang(string $lang): void {
        $this->requireAdmin();
        if (Language::isUsable($lang, 'ui')) {
            setcookie(self::ADMIN_LANG_COOKIE, $lang, [
                'expires' => time() + 86400 * 365, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                'secure' => (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            ]);
        }
        $back = (string)($_GET['back'] ?? '');
        header('Location: ' . (preg_match('/^\?page=admin-[a-z0-9-]+[^\r\n]*$/', $back) ? $back : '?page=admin-dashboard'));
        exit;
    }

    /** The logged-in admin row, set by requireAdmin() (for the layout header). */
    private ?array $currentAdmin = null;

    /** Blocks 'viewer' admins from write actions; call after requireAdmin(). */
    private function requireFullAdmin(): void {
        if (($_SESSION['admin_role'] ?? 'admin') !== 'admin') {
            http_response_code(403);
            exit('Forbidden — your admin account is read-only.');
        }
    }

    /** CSRF token generation */
    private function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Verify CSRF token */
    private function validateCsrfToken(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    // ------------------- Auth -------------------
    public function showLogin(): void {
        Language::load(self::adminLang());
        if (!empty($_SESSION['admin_id']) && time() - (int)($_SESSION['admin_last_seen'] ?? 0) <= self::IDLE_TIMEOUT) {
            header('Location: ?page=admin-dashboard');
            exit;
        }
        $csrf = $this->generateCsrfToken();
        $loginError = $_SESSION['admin_login_error'] ?? '';
        unset($_SESSION['admin_login_error']);
        require __DIR__ . '/../views/admin/login.php';
    }

    /**
     * Admin login shares the same login_attempts table as regular user
     * login (Auth::tooManyAttempts et al.), just under its own 'admin-login'
     * type, so a brute-force run against one doesn't get counted against —
     * or accidentally cleared by — the other.
     */
    private function adminLoginTooManyAttempts(string $ip): bool {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND type = 'admin-login' AND attempted_at > CURRENT_TIMESTAMP - INTERVAL '900 seconds'",
            [$ip]
        );
        return $row && (int)$row['cnt'] >= 8;
    }

    private function recordAdminLoginAttempt(string $ip): void {
        $this->db->execute("INSERT INTO login_attempts (ip, type) VALUES (?, 'admin-login')", [$ip]);
    }

    private function clearAdminLoginAttempts(string $ip): void {
        $this->db->execute("DELETE FROM login_attempts WHERE ip = ? AND type = 'admin-login'", [$ip]);
    }

    public function handleLogin(array $post): void {
        Language::load(self::adminLang());
        $email    = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';
        $csrf     = $post['csrf'] ?? '';
        $ip       = client_ip();
        if (!$this->validateCsrfToken($csrf)) {
            $_SESSION['admin_login_error'] = t('admin.csrf_failed');
            header('Location: ?page=admin-login');
            exit;
        }
        if ($this->adminLoginTooManyAttempts($ip)) {
            $_SESSION['admin_login_error'] = t('admin.err_too_many');
            header('Location: ?page=admin-login');
            exit;
        }
        $admin = $this->db->fetchOne('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($admin && password_verify($password, $admin['password'])) {
            $this->clearAdminLoginAttempts($ip);
            if (!empty($admin['totp_enabled_at'])) {
                // Password was right; the session only becomes an admin
                // session after the authenticator code (step 2).
                session_regenerate_id(true);
                $_SESSION['admin_2fa_pending'] = ['id' => (int)$admin['id'], 'at' => time()];
                header('Location: ?page=admin-login-2fa');
                exit;
            }
            $this->completeLogin($admin, 'admin_login');
        }
        $this->recordAdminLoginAttempt($ip);
        $_SESSION['admin_login_error'] = t('admin.err_bad_login');
        header('Location: ?page=admin-login');
        exit;
    }

    /** Final step of a successful sign-in (with or without 2FA). */
    private function completeLogin(array $admin, string $auditAction): void {
        // New session id on privilege change (session fixation).
        session_regenerate_id(true);
        unset($_SESSION['admin_2fa_pending']);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_last_seen'] = time();
        $this->currentAdmin = $admin;
        $this->audit($auditAction, null, empty($admin['totp_enabled_at']) ? t('admin.audit_2fa_off') : '');
        header('Location: ?page=admin-dashboard');
        exit;
    }

    /** Pending 2FA login (password already verified), or null if absent / expired (5 min). */
    private function pending2fa(): ?array {
        $p = $_SESSION['admin_2fa_pending'] ?? null;
        if (!$p || time() - (int)$p['at'] > 300) {
            unset($_SESSION['admin_2fa_pending']);
            return null;
        }
        return $this->db->fetchOne('SELECT * FROM admins WHERE id = ? AND totp_enabled_at IS NOT NULL', [(int)$p['id']]);
    }

    public function showLogin2fa(): void {
        Language::load(self::adminLang());
        $admin = $this->pending2fa();
        if (!$admin) {
            $_SESSION['admin_login_error'] = t('admin.err_2fa_expired');
            header('Location: ?page=admin-login');
            exit;
        }
        $csrf = $this->generateCsrfToken();
        $loginError = $_SESSION['admin_login_error'] ?? '';
        unset($_SESSION['admin_login_error']);
        $step = '2fa';
        $pendingEmail = $admin['email'];
        require __DIR__ . '/../views/admin/login.php';
    }

    public function handleLogin2fa(array $post): void {
        Language::load(self::adminLang());
        $admin = $this->pending2fa();
        $ip = client_ip();
        if (!$admin) {
            $_SESSION['admin_login_error'] = t('admin.err_2fa_expired');
            header('Location: ?page=admin-login');
            exit;
        }
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $_SESSION['admin_login_error'] = t('admin.csrf_failed');
            header('Location: ?page=admin-login-2fa');
            exit;
        }
        // Same per-IP budget as the password step (8 tries / 15 min).
        if ($this->adminLoginTooManyAttempts($ip)) {
            unset($_SESSION['admin_2fa_pending']);
            $_SESSION['admin_login_error'] = t('admin.err_too_many');
            header('Location: ?page=admin-login');
            exit;
        }
        $code = trim((string)($post['code'] ?? ''));
        if (Totp::verify((string)$admin['totp_secret'], $code)) {
            $this->clearAdminLoginAttempts($ip);
            $this->completeLogin($admin, 'admin_login_2fa');
        }
        $remaining = Totp::useBackupCode(json_decode((string)$admin['totp_backup_codes'], true) ?: [], $code);
        if ($remaining !== null) {
            $this->db->execute('UPDATE admins SET totp_backup_codes = ? WHERE id = ?', [json_encode($remaining), $admin['id']]);
            $this->clearAdminLoginAttempts($ip);
            $this->currentAdmin = $admin;
            $this->audit('admin_backup_code_used', null, t('admin.backup_codes_left', ['n' => count($remaining)]));
            $this->completeLogin($admin, 'admin_login_2fa');
        }
        $this->recordAdminLoginAttempt($ip);
        $_SESSION['admin_login_error'] = t('admin.err_bad_code');
        header('Location: ?page=admin-login-2fa');
        exit;
    }

    public function logout(): void {
        unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_last_seen'], $_SESSION['admin_2fa_pending'], $_SESSION['admin_totp_setup']);
        session_regenerate_id(true);
        header('Location: ?page=admin-login');
        exit;
    }

    // ------------------- Dashboard -------------------
    public function dashboard(): void {
        $this->requireAdmin();
        $range = (int)($_GET['d'] ?? 30);
        $range = in_array($range, [7, 30, 90], true) ? $range : 30;
        $stats = new AdminStats($this->db);
        (new AdminRevenue($this->db))->snapshot();
        $kpis = $stats->kpis($range);
        $revenue = $stats->revenue();
        $daily = $stats->daily($range);
        $funnel = $stats->funnel($range);
        $languages = $stats->languages();
        $plans = $stats->plans();
        $todos = $stats->todos();
        $recentSignups = $stats->recentSignups();
        $recentEvents = $stats->recentEvents();
        $admin = $this->currentAdmin;
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    // ------------------- Audit -------------------
    /** Writes one admin_audit row. Never throws — an audit failure must not block the action. */
    private function audit(string $action, ?int $targetUserId = null, string $detail = ''): void {
        try {
            $this->db->execute(
                'INSERT INTO admin_audit (admin_id, admin_email, action, target_user_id, detail, ip) VALUES (?, ?, ?, ?, ?, ?)',
                [$_SESSION['admin_id'] ?? null, $this->currentAdmin['email'] ?? null, $action, $targetUserId, mb_substr($detail, 0, 1000), client_ip()]
            );
        } catch (\Throwable $e) {
            error_log('admin audit failed: ' . $e->getMessage());
        }
    }

    private function flash(string $type, string $message): void {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }

    // ------------------- Users -------------------
    public function listUsers(array $query): void {
        $this->requireAdmin();
        $filters = [
            'q' => trim((string)($query['q'] ?? '')),
            'plan' => (string)($query['plan'] ?? ''),
            'pay' => (string)($query['pay'] ?? ''),
            'lang' => (string)($query['lang'] ?? ''),
            'status' => (string)($query['status'] ?? ''),
            'sort' => (string)($query['sort'] ?? 'new'),
        ];
        $pageNum = max(1, (int)($query['p'] ?? 1));
        $result = (new AdminUsers($this->db))->search($filters, $pageNum);
        require __DIR__ . '/../views/admin/users.php';
    }

    public function userDetail(int $id): void {
        $this->requireAdmin();
        $usersSvc = new AdminUsers($this->db);
        $user = $usersSvc->find($id);
        if (!$user) {
            $this->flash('danger', t('admin.err_user_id_missing', ['id' => $id]));
            header('Location: ?page=admin-users');
            exit;
        }
        $details = $usersSvc->details($id);
        $quotaLimit = (new TokenManager($this->db))->getBaseLimit((string)$user['plan_status']);
        $csrf = $this->generateCsrfToken();
        $canEdit = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
        require __DIR__ . '/../views/admin/user_detail.php';
    }

    /** POST target of every button on the user detail page. */
    public function userAction(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        $id = (int)($post['id'] ?? 0);
        $back = '?page=admin-user&id=' . $id;
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $this->flash('danger', t('admin.csrf_failed'));
            header('Location: ' . $back);
            exit;
        }
        $usersSvc = new AdminUsers($this->db);
        $user = $usersSvc->find($id);
        if (!$user) {
            $this->flash('danger', t('admin.err_user_missing'));
            header('Location: ?page=admin-users');
            exit;
        }
        $action = (string)($post['action'] ?? '');
        $value = trim((string)($post['value'] ?? ''));
        try {
            switch ($action) {
                case 'set_plan':
                    $msg = $usersSvc->setPlan($user, $value);
                    $this->audit('user_plan_changed', $id, "{$user['plan_status']} -> {$value}");
                    break;
                case 'add_bonus':
                    $msg = $usersSvc->addBonus($id, (int)$value);
                    $this->audit('user_bonus_added', $id, '+' . (int)$value . ' messages');
                    break;
                case 'reset_usage':
                    $msg = $usersSvc->resetUsage($id);
                    $this->audit('user_usage_reset', $id);
                    break;
                case 'verify_email':
                    $msg = $usersSvc->verifyEmail($id);
                    $this->audit('user_email_verified', $id);
                    break;
                case 'send_reset':
                    $token = (new Auth($this->db))->createPasswordResetToken($id);
                    $resetUrl = 'https://jumplearner.com/?page=reset-password&token=' . urlencode($token);
                    $sent = (new Mailer($this->config))->send(
                        $user['email'],
                        __('auth.reset_email_subject'),
                        '<p>' . __('auth.reset_email_body') . '</p><p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a></p>'
                    );
                    if (!$sent) {
                        throw new \RuntimeException(t('admin.err_mail_failed'));
                    }
                    $msg = t('admin.reset_sent', ['email' => $user['email']]);
                    $this->audit('user_password_reset_sent', $id);
                    break;
                case 'suspend':
                case 'unsuspend':
                    $msg = $usersSvc->setSuspended($id, $action === 'suspend');
                    $this->audit($action === 'suspend' ? 'user_suspended' : 'user_unsuspended', $id, $value);
                    break;
                case 'delete':
                    if (mb_strtolower($value) !== mb_strtolower((string)$user['email'])) {
                        throw new \InvalidArgumentException(t('admin.err_type_email'));
                    }
                    $this->deleteUser($user);
                    $this->flash('success', t('admin.user_deleted_done', ['email' => $user['email']]));
                    header('Location: ?page=admin-users');
                    exit;
                default:
                    throw new \InvalidArgumentException(t('admin.err_unknown_action'));
            }
            $this->flash('success', $msg);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
        }
        header('Location: ' . $back);
        exit;
    }

    /**
     * Same erasure as the user's own "delete account": stop real billing
     * first (best effort), then remove the user — everything they own
     * cascades except token_usage.
     */
    private function deleteUser(array $user): void {
        $id = (int)$user['id'];
        if (!empty($user['dodo_subscription_id'])) {
            $dodo = new DodoClient($this->config['dodo_api_key'] ?? '', $this->config['dodo_environment'] ?? 'live');
            if ($dodo->isConfigured()) {
                $dodo->cancelSubscription($user['dodo_subscription_id']);
            }
        }
        $this->audit('user_deleted', $id, $user['email'] . ' (' . $user['plan_status'] . ')');
        ActivityLog::record($this->db, $id, 'account_deleted', null, $user['plan_status'] ?? null, null, "admin deleted user {$id} ({$user['email']})");
        $this->db->execute('DELETE FROM token_usage WHERE user_id = ?', [$id]);
        $this->db->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    // ------------------- Admins -------------------
    public function listAdmins(): void {
        $this->requireAdmin();
        if (isset($_SESSION['admin_admins_error'])) {
            $adminsError = $_SESSION['admin_admins_error'];
            unset($_SESSION['admin_admins_error']);
        }
        $admins = $this->db->fetchAll('SELECT id, email, name, role, created_at FROM admins ORDER BY id');
        $csrf = $this->generateCsrfToken();
        require __DIR__ . '/../views/admin/admins.php';
    }

    public function createAdmin(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken($post['csrf'] ?? '')) {
            $_SESSION['admin_admins_error'] = t('admin.csrf_failed');
            header('Location: ?page=admin-admins');
            exit;
        }
        $email = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';
        $role = ($post['role'] ?? 'viewer') === 'admin' ? 'admin' : 'viewer';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $_SESSION['admin_admins_error'] = t('admin.err_admin_form');
            header('Location: ?page=admin-admins');
            exit;
        }
        try {
            $this->db->execute(
                'INSERT INTO admins (email, password, name, role) VALUES (?, ?, ?, ?)',
                [$email, password_hash($password, PASSWORD_BCRYPT), $post['name'] ?? '', $role]
            );
            $this->audit('admin_created', null, "{$email} ({$role})");
        } catch (\PDOException $e) {
            $_SESSION['admin_admins_error'] = str_contains($e->getMessage(), 'duplicate key')
                ? t('admin.err_admin_exists')
                : t('admin.err_admin_create');
        }
        header('Location: ?page=admin-admins');
        exit;
    }

    public function deleteAdmin(int $id): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken($_POST['csrf'] ?? '')) {
            $_SESSION['admin_admins_error'] = t('admin.csrf_failed');
            header('Location: ?page=admin-admins');
            exit;
        }
        if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_admins_error'] = t('admin.err_remove_self');
            header('Location: ?page=admin-admins');
            exit;
        }
        $removed = $this->db->fetchOne('SELECT email FROM admins WHERE id = ?', [$id]);
        $this->db->execute('DELETE FROM admins WHERE id = ?', [$id]);
        if ($removed) {
            $this->audit('admin_deleted', null, $removed['email']);
        }
        header('Location: ?page=admin-admins');
        exit;
    }

    // ------------------- Payments -------------------
    // ------------------- Revenue & subscriptions -------------------
    public function revenue(array $query): void {
        $this->requireAdmin();
        $rev = new AdminRevenue($this->db);
        $rev->snapshot();
        $filters = [
            'type' => in_array($query['type'] ?? 'real', ['real', 'test', 'all'], true) ? ($query['type'] ?? 'real') : 'real',
            'status' => (string)($query['status'] ?? ''),
            'q' => trim((string)($query['q'] ?? '')),
        ];
        $pageNum = max(1, (int)($query['p'] ?? 1));
        $kpis = $rev->kpis();
        $history = $rev->history(90);
        $movements = $rev->monthlyMovements(6);
        $planMix = $rev->planMix();
        $queue = $rev->queue();
        $handled = $rev->recentlyHandled();
        $renewals = $rev->upcomingRenewals(7);
        $subs = $rev->subscriptions($filters, $pageNum);
        $csrf = $this->generateCsrfToken();
        $canEdit = ($_SESSION['admin_role'] ?? 'admin') === 'admin';
        require __DIR__ . '/../views/admin/revenue.php';
    }

    /** Closes a refund / manual-cancellation request from the work queue. */
    public function revenueAction(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $this->flash('danger', t('admin.csrf_failed'));
            header('Location: ?page=admin-payments');
            exit;
        }
        $userId = (int)($post['id'] ?? 0);
        $kind = (string)($post['kind'] ?? '');
        $outcome = (string)($post['outcome'] ?? '');
        $outcomes = [
            'refund' => ['refunded' => t('admin.o_refunded'), 'declined' => t('admin.o_refund_declined')],
            'cancel' => ['canceled' => t('admin.o_canceled'), 'kept' => t('admin.o_kept')],
        ];
        $note = mb_substr(trim((string)($post['note'] ?? '')), 0, 300);
        try {
            if (!isset($outcomes[$kind][$outcome])) {
                throw new \InvalidArgumentException(t('admin.err_pick_outcome'));
            }
            $msg = (new AdminRevenue($this->db))->resolve($userId, $kind);
            $this->audit($kind === 'refund' ? 'refund_handled' : 'cancellation_handled', $userId,
                $outcomes[$kind][$outcome] . ($note !== '' ? ' — ' . $note : ''));
            $this->flash('success', $msg . ' (' . $outcomes[$kind][$outcome] . ')');
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        header('Location: ?page=admin-payments');
        exit;
    }

    // ------------------- Activity monitor -------------------
    public function listActivity(array $query): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, (int)($query['p'] ?? 1));
        $filters = [
            'type' => (string)($query['type'] ?? ''),
            'provider' => (string)($query['provider'] ?? ''),
        ];
        $where = ['TRUE'];
        $params = [];
        if ($filters['type'] !== '') {
            $where[] = 'ae.event_type = ?';
            $params[] = $filters['type'];
        }
        if ($filters['provider'] !== '') {
            $where[] = 'ae.provider = ?';
            $params[] = $filters['provider'];
        }
        $whereSql = implode(' AND ', $where);
        $totalCount = (int)$this->db->fetchOne("SELECT COUNT(*) AS cnt FROM activity_events ae WHERE {$whereSql}", $params)['cnt'];
        $events = $this->db->fetchAll(
            "SELECT ae.id, ae.user_id, ae.event_type, ae.provider, ae.plan, ae.billing_interval, ae.detail, ae.created_at, u.email AS user_email
             FROM activity_events ae LEFT JOIN users u ON u.id = ae.user_id
             WHERE {$whereSql} ORDER BY ae.created_at DESC
             LIMIT {$perPage} OFFSET " . (($pageNum - 1) * $perPage),
            $params
        );
        $eventTypes = array_column($this->db->fetchAll('SELECT DISTINCT event_type FROM activity_events ORDER BY 1'), 'event_type');
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/activity.php';
    }

    // ------------------- Conversations -------------------
    public function listConversations(array $query): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, (int)($query['p'] ?? 1));
        $search = trim((string)($query['q'] ?? ''));
        $where = 'TRUE';
        $params = [];
        if ($search !== '') {
            $where = '(u.email ILIKE ? OR CAST(c.id AS TEXT) = ?)';
            $params = ['%' . $search . '%', ltrim($search, '#')];
        }
        $totalCount = (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM conversations c JOIN users u ON u.id = c.user_id WHERE {$where}", $params)['c'];
        $convs = $this->db->fetchAll(
            "SELECT c.id, c.user_id, u.email AS user_email, c.topic_id, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.role = 'user') AS user_messages,
                    (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id LIMIT 1) AS first_message
             FROM conversations c JOIN users u ON u.id = c.user_id
             WHERE {$where} ORDER BY c.updated_at DESC LIMIT {$perPage} OFFSET " . (($pageNum - 1) * $perPage),
            $params
        );
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/conversations.php';
    }

    public function viewConversation(int $convId): void {
        $this->requireAdmin();
        $conv = $this->db->fetchOne(
            'SELECT c.id, c.user_id, c.topic_id, c.created_at, c.updated_at, u.email AS user_email, u.name AS user_name, u.target_lang, u.native_lang
             FROM conversations c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?',
            [$convId]
        );
        if (!$conv) {
            $this->flash('danger', t('admin.err_conv_missing', ['id' => $convId]));
            header('Location: ?page=admin-conversations');
            exit;
        }
        // Reading a user's private chat is logged.
        $this->audit('conversation_viewed', (int)$conv['user_id'], "conversation #{$convId}");
        $messages = $this->db->fetchAll('SELECT role, content, translation, correction, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC', [$convId]);
        require __DIR__ . '/../views/admin/conversation_detail.php';
    }

    // ------------------- Languages & UI strings -------------------
    public function languages(): void {
        $this->requireAdmin();
        $tr = new UiTranslator($this->db);
        $registry = Language::registry();
        $coverage = $tr->coverage();
        $geminiReady = !empty($this->config['gemini_api_key']);
        $csrf = $this->generateCsrfToken();
        require __DIR__ . '/../views/admin/languages.php';
    }

    public function languagesAction(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $this->flash('danger', t('admin.csrf_failed'));
            header('Location: ?page=admin-languages');
            exit;
        }
        $tr = new UiTranslator($this->db);
        $code = strtolower((string)($post['code'] ?? ''));
        try {
            switch ((string)($post['action'] ?? '')) {
                case 'update':
                    $status = (string)($post['status'] ?? 'draft');
                    $tr->updateLanguage($code, $status, !empty($post['ui_enabled']), !empty($post['learn_enabled']));
                    $this->audit('language_updated', null, "{$code}: {$status}" . (empty($post['ui_enabled']) ? ', no UI' : '') . (empty($post['learn_enabled']) ? ', not learnable' : ''));
                    $this->flash('success', t('admin.lang_saved', ['code' => $code]));
                    break;
                case 'add':
                    $tr->addLanguage($code, (string)($post['name'] ?? ''), (string)($post['native_name'] ?? ''),
                        (string)($post['flag'] ?? ''), (string)($post['dir'] ?? 'ltr'), (string)($post['speech_locale'] ?? ''));
                    $this->audit('language_added', null, $code);
                    $this->flash('success', t('admin.lang_added', ['code' => $code]));
                    break;
                case 'clear_ai':
                    $n = $tr->clearAi($code);
                    $this->audit('language_ai_cleared', null, "{$code}: {$n} strings");
                    $this->flash('success', t('admin.lang_ai_cleared', ['n' => $n, 'code' => $code]));
                    break;
                default:
                    throw new \InvalidArgumentException('unknown action');
            }
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        header('Location: ?page=admin-languages');
        exit;
    }

    /** JSON: translates one batch of missing strings; the page calls it until remaining = 0. */
    public function languageTranslate(array $post): void {
        $this->requireAdmin();
        header('Content-Type: application/json');
        if (($_SESSION['admin_role'] ?? 'admin') !== 'admin' || !$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            echo json_encode(['ok' => false, 'error' => t('admin.csrf_failed')]);
            exit;
        }
        $code = strtolower((string)($post['code'] ?? ''));
        try {
            $gemini = new GeminiClient($this->config['gemini_api_key'] ?? '', $this->config['gemini_api_key_backup'] ?? '');
            $r = (new UiTranslator($this->db))->translateBatch($code, $gemini);
            if ($r['translated'] > 0) {
                $this->audit('language_ai_translated', null, "{$code}: +{$r['translated']}");
            }
            echo json_encode(['ok' => true] + $r);
        } catch (\Throwable $e) {
            error_log('languageTranslate: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => $e instanceof \InvalidArgumentException ? $e->getMessage() : t('admin.lang_ai_failed')]);
        }
        exit;
    }

    public function languageStrings(array $query): void {
        $this->requireAdmin();
        $code = strtolower((string)($query['code'] ?? ''));
        $lang = Language::info($code);
        if (!$lang) {
            header('Location: ?page=admin-languages');
            exit;
        }
        $filter = in_array($query['filter'] ?? '', ['missing', 'ai', 'manual'], true) ? $query['filter'] : 'all';
        $search = mb_substr(trim((string)($query['q'] ?? '')), 0, 100);
        $rows = (new UiTranslator($this->db))->rows($code, $filter, $search);
        $perPage = 50;
        $total = count($rows);
        $pageNo = max(1, min((int)($query['p'] ?? 1), max(1, (int)ceil($total / $perPage))));
        $rows = array_slice($rows, ($pageNo - 1) * $perPage, $perPage);
        $csrf = $this->generateCsrfToken();
        require __DIR__ . '/../views/admin/language_strings.php';
    }

    public function languageStringAction(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        $code = strtolower((string)($post['code'] ?? ''));
        $back = '?page=admin-language-strings&' . http_build_query([
            'code' => $code, 'filter' => (string)($post['filter'] ?? 'all'),
            'q' => (string)($post['q'] ?? ''), 'p' => (int)($post['p'] ?? 1),
        ]);
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $this->flash('danger', t('admin.csrf_failed'));
            header('Location: ' . $back);
            exit;
        }
        $key = (string)($post['key'] ?? '');
        $tr = new UiTranslator($this->db);
        try {
            if (($post['action'] ?? '') === 'reset') {
                $tr->resetString($code, $key);
            } else {
                $value = trim((string)($post['value'] ?? ''));
                if ($value === '') {
                    throw new \InvalidArgumentException(t('admin.lang_empty_value'));
                }
                $tr->setString($code, $key, $value);
            }
            $this->audit('ui_string_edited', null, "{$code}:{$key}");
            $this->flash('success', t('admin.lang_string_saved', ['key' => $key]));
        } catch (\InvalidArgumentException $e) {
            $this->flash('danger', $e->getMessage());
        }
        header('Location: ' . $back . '#k-' . rawurlencode($key));
        exit;
    }

    /** Downloads the merged strings of one language as lang/<code>.json. */
    public function languageExport(string $code): void {
        $this->requireAdmin();
        $code = strtolower($code);
        if (!Language::info($code)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $code . '.json"');
        echo json_encode((new UiTranslator($this->db))->export($code), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        exit;
    }

    // ------------------- AI usage -------------------
    public function aiUsage(): void {
        $this->requireAdmin();
        $usage = new AiUsage($this->db);
        $monthStart = "date_trunc('month', CURRENT_TIMESTAMP)";
        $periods = [
            'today' => $usage->totals('CURRENT_DATE'),
            'week'  => $usage->totals("CURRENT_TIMESTAMP - INTERVAL '7 days'"),
            'month' => $usage->totals($monthStart),
        ];
        $byModel = $usage->byModel($monthStart);
        $byPlan = $usage->byPlan($monthStart);
        $topUsers = $usage->topUsers($monthStart);
        $daily = $usage->daily(14);
        $dayOfMonth = (int)date('j');
        $daysInMonth = (int)date('t');
        $monthCost = (float)($periods['month']['cost'] ?? 0);
        $projectedMonthCost = $dayOfMonth > 0 ? $monthCost / $dayOfMonth * $daysInMonth : 0;
        $prices = self::MONTHLY_PRICE_USD;
        $quota = new TokenManager($this->db);
        $modelPrices = AiUsage::PRICES;
        require __DIR__ . '/../views/admin/ai_usage.php';
    }

    // ------------------- System -------------------
    /** Read-only configuration status (replaces the old .env editor, which couldn't work on Railway). */
    public function settings(): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        $groups = (new AdminHealth($this->db, $this->config))->config();
        require __DIR__ . '/../views/admin/settings.php';
    }

    public function health(): void {
        $this->requireAdmin();
        $h = new AdminHealth($this->db, $this->config);
        $version = $h->version();
        $database = $h->database();
        $webhooks = $h->webhooks();
        $ai = $h->ai();
        $security = $h->security();
        $config = $h->config();
        require __DIR__ . '/../views/admin/health.php';
    }

    public function auditLog(array $query): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, (int)($query['p'] ?? 1));
        $filters = ['action' => (string)($query['action'] ?? ''), 'admin' => (string)($query['admin'] ?? ''), 'user' => (int)($query['user'] ?? 0)];
        $where = ['TRUE'];
        $params = [];
        if ($filters['action'] !== '') { $where[] = 'a.action = ?'; $params[] = $filters['action']; }
        if ($filters['admin'] !== '') { $where[] = 'a.admin_email = ?'; $params[] = $filters['admin']; }
        if ($filters['user'] > 0) { $where[] = 'a.target_user_id = ?'; $params[] = $filters['user']; }
        $whereSql = implode(' AND ', $where);
        $totalCount = (int)$this->db->fetchOne("SELECT COUNT(*) AS c FROM admin_audit a WHERE {$whereSql}", $params)['c'];
        $rows = $this->db->fetchAll(
            "SELECT a.*, u.email AS user_email FROM admin_audit a LEFT JOIN users u ON u.id = a.target_user_id
             WHERE {$whereSql} ORDER BY a.performed_at DESC LIMIT {$perPage} OFFSET " . (($pageNum - 1) * $perPage),
            $params
        );
        $actions = array_column($this->db->fetchAll('SELECT DISTINCT action FROM admin_audit ORDER BY 1'), 'action');
        $adminEmails = array_column($this->db->fetchAll('SELECT DISTINCT admin_email FROM admin_audit WHERE admin_email IS NOT NULL ORDER BY 1'), 'admin_email');
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/audit.php';
    }

    // ------------------- Two-factor auth (own account) -------------------
    public function twoFactor(): void {
        $this->requireAdmin();
        $admin = $this->currentAdmin;
        $enabled = !empty($admin['totp_enabled_at']);
        if (!$enabled && empty($_SESSION['admin_totp_setup'])) {
            $_SESSION['admin_totp_setup'] = Totp::generateSecret();
        }
        $setupSecret = $enabled ? null : $_SESSION['admin_totp_setup'];
        $setupUri = $setupSecret ? Totp::uri($setupSecret, (string)$admin['email']) : null;
        $backupLeft = count(json_decode((string)($admin['totp_backup_codes'] ?? ''), true) ?: []);
        $newBackupCodes = $_SESSION['admin_new_backup_codes'] ?? null; // shown exactly once
        unset($_SESSION['admin_new_backup_codes']);
        $csrf = $this->generateCsrfToken();
        require __DIR__ . '/../views/admin/two_factor.php';
    }

    public function twoFactorAction(array $post): void {
        $this->requireAdmin();
        $admin = $this->currentAdmin;
        $back = '?page=admin-2fa';
        if (!$this->validateCsrfToken((string)($post['csrf'] ?? ''))) {
            $this->flash('danger', t('admin.csrf_failed'));
            header('Location: ' . $back);
            exit;
        }
        $code = trim((string)($post['code'] ?? ''));
        $action = (string)($post['action'] ?? '');
        if ($action === 'enable') {
            $secret = (string)($_SESSION['admin_totp_setup'] ?? '');
            if ($secret === '' || !Totp::verify($secret, $code)) {
                $this->flash('danger', t('admin.err_code_verify'));
                header('Location: ' . $back);
                exit;
            }
            [$plain, $hashes] = Totp::backupCodes();
            $this->db->execute('UPDATE admins SET totp_secret = ?, totp_enabled_at = CURRENT_TIMESTAMP, totp_backup_codes = ? WHERE id = ?', [$secret, json_encode($hashes), $admin['id']]);
            unset($_SESSION['admin_totp_setup']);
            $_SESSION['admin_new_backup_codes'] = $plain;
            $this->audit('admin_2fa_enabled');
            $this->flash('success', t('admin.twofa_enabled_done'));
        } elseif ($action === 'disable' || $action === 'regenerate') {
            // A backup code works here too, so a lost phone can still turn 2FA off.
            $ok = !empty($admin['totp_enabled_at']) && Totp::verify((string)$admin['totp_secret'], $code);
            if (!$ok && !empty($admin['totp_enabled_at'])) {
                $remaining = Totp::useBackupCode(json_decode((string)$admin['totp_backup_codes'], true) ?: [], $code);
                if ($remaining !== null) {
                    $this->db->execute('UPDATE admins SET totp_backup_codes = ? WHERE id = ?', [json_encode($remaining), $admin['id']]);
                    $ok = true;
                }
            }
            if (!$ok) {
                $this->flash('danger', t('admin.err_bad_code_short'));
                header('Location: ' . $back);
                exit;
            }
            if ($action === 'disable') {
                $this->db->execute('UPDATE admins SET totp_secret = NULL, totp_enabled_at = NULL, totp_backup_codes = NULL WHERE id = ?', [$admin['id']]);
                $this->audit('admin_2fa_disabled');
                $this->flash('success', t('admin.twofa_disabled_done'));
            } else {
                [$plain, $hashes] = Totp::backupCodes();
                $this->db->execute('UPDATE admins SET totp_backup_codes = ? WHERE id = ?', [json_encode($hashes), $admin['id']]);
                $_SESSION['admin_new_backup_codes'] = $plain;
                $this->audit('admin_2fa_backup_regenerated');
                $this->flash('success', t('admin.codes_regenerated'));
            }
        }
        header('Location: ' . $back);
        exit;
    }

    // ------------------- CSV Export -------------------
    public function exportCsv(string $type): void {
        $this->requireAdmin();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '.csv"');
        $output = fopen('php://output', 'w');
        if ($type === 'users') {
            fputcsv($output, ['id', 'email', 'name', 'xp', 'has_paid', 'plan_status']);
            $rows = $this->db->fetchAll('SELECT id, email, name, xp, has_paid, plan_status FROM users');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        } elseif ($type === 'payments') {
            fputcsv($output, ['user_id', 'email', 'plan_status', 'has_paid', 'created_at']);
            $rows = $this->db->fetchAll('SELECT u.id, u.email, u.plan_status, u.has_paid, u.created_at FROM users u WHERE u.has_paid = 1');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        } elseif ($type === 'admins') {
            fputcsv($output, ['id', 'email', 'name', 'role', 'created_at']);
            $rows = $this->db->fetchAll('SELECT id, email, name, role, created_at FROM admins ORDER BY id');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
}
?>
