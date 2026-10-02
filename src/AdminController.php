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
        // The admin panel is Turkish regardless of any site user's language.
        Language::load('tr');
        $adminId = $_SESSION['admin_id'] ?? null;
        if (!$adminId) {
            header('Location: ?page=admin-login');
            exit;
        }
        // Admin access shares the site's 90-day session cookie, so it gets
        // its own idle timeout on top.
        if (time() - (int)($_SESSION['admin_last_seen'] ?? 0) > self::IDLE_TIMEOUT) {
            unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_last_seen']);
            $_SESSION['admin_login_error'] = 'Oturum süresi doldu, lütfen tekrar giriş yap.';
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
        Language::load('tr');
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
        $email    = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';
        $csrf     = $post['csrf'] ?? '';
        $ip       = client_ip();
        if (!$this->validateCsrfToken($csrf)) {
            $_SESSION['admin_login_error'] = 'Oturum doğrulanamadı, sayfayı yenileyip tekrar dene.';
            header('Location: ?page=admin-login');
            exit;
        }
        if ($this->adminLoginTooManyAttempts($ip)) {
            $_SESSION['admin_login_error'] = 'Çok fazla başarısız deneme. 15 dakika sonra tekrar dene.';
            header('Location: ?page=admin-login');
            exit;
        }
        $admin = $this->db->fetchOne('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($admin && password_verify($password, $admin['password'])) {
            $this->clearAdminLoginAttempts($ip);
            // New session id on privilege change (session fixation).
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_last_seen'] = time();
            header('Location: ?page=admin-dashboard');
            exit;
        }
        $this->recordAdminLoginAttempt($ip);
        $_SESSION['admin_login_error'] = 'E-posta veya şifre hatalı.';
        header('Location: ?page=admin-login');
        exit;
    }

    public function logout(): void {
        unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_last_seen']);
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

    // ------------------- Users -------------------
    public function listUsers(int $pageNum = 1, string $search = ''): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, $pageNum);
        $offset = ($pageNum - 1) * $perPage;
        $search = trim($search);
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE email ILIKE ?';
            $params[] = '%' . $search . '%';
        }
        $totalCount = (int)$this->db->fetchOne('SELECT COUNT(*) as cnt FROM users' . $where, $params)['cnt'];
        $users = $this->db->fetchAll(
            'SELECT id, email, name, xp, has_paid, plan_status FROM users' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/users.php';
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
            $_SESSION['admin_admins_error'] = 'Invalid CSRF token.';
            header('Location: ?page=admin-admins');
            exit;
        }
        $email = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';
        $role = ($post['role'] ?? 'viewer') === 'admin' ? 'admin' : 'viewer';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $_SESSION['admin_admins_error'] = 'Enter a valid email and a password of at least 8 characters.';
            header('Location: ?page=admin-admins');
            exit;
        }
        try {
            $this->db->execute(
                'INSERT INTO admins (email, password, name, role) VALUES (?, ?, ?, ?)',
                [$email, password_hash($password, PASSWORD_BCRYPT), $post['name'] ?? '', $role]
            );
        } catch (\PDOException $e) {
            $_SESSION['admin_admins_error'] = str_contains($e->getMessage(), 'duplicate key')
                ? 'An admin with that email already exists.'
                : 'Could not create admin.';
        }
        header('Location: ?page=admin-admins');
        exit;
    }

    public function deleteAdmin(int $id): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken($_POST['csrf'] ?? '')) {
            $_SESSION['admin_admins_error'] = 'Invalid CSRF token.';
            header('Location: ?page=admin-admins');
            exit;
        }
        if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_admins_error'] = "You can't remove your own admin account.";
            header('Location: ?page=admin-admins');
            exit;
        }
        $this->db->execute('DELETE FROM admins WHERE id = ?', [$id]);
        header('Location: ?page=admin-admins');
        exit;
    }

    // ------------------- Payments -------------------
    public function listPayments(int $pageNum = 1, string $search = ''): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, $pageNum);
        $offset = ($pageNum - 1) * $perPage;
        $search = trim($search);
        $where = 'WHERE u.has_paid = 1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND u.email ILIKE ?';
            $params[] = '%' . $search . '%';
        }
        $totalCount = (int)$this->db->fetchOne("SELECT COUNT(*) as cnt FROM users u {$where}", $params)['cnt'];
        $payments = $this->db->fetchAll(
            "SELECT u.id, u.email, u.plan_status, u.has_paid, u.created_at, u.paddle_subscription_id, u.fastspring_subscription_id, u.dodo_subscription_id, u.cancel_requested_at, u.cancel_method, u.next_billed_at, u.pending_plan_change, u.refund_requested_at
             FROM users u {$where} ORDER BY u.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/payments.php';
    }

    // ------------------- Activity monitor -------------------
    public function listActivity(int $pageNum = 1): void {
        $this->requireAdmin();
        $perPage = 50;
        $pageNum = max(1, $pageNum);
        $offset = ($pageNum - 1) * $perPage;
        $totalCount = (int)$this->db->fetchOne('SELECT COUNT(*) as cnt FROM activity_events')['cnt'];
        $events = $this->db->fetchAll(
            'SELECT ae.id, ae.event_type, ae.provider, ae.plan, ae.billing_interval, ae.detail, ae.created_at, u.email as user_email
             FROM activity_events ae
             LEFT JOIN users u ON u.id = ae.user_id
             ORDER BY ae.created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        require __DIR__ . '/../views/admin/activity.php';
    }

    // ------------------- Conversations -------------------
    public function listConversations(): void {
        $this->requireAdmin();
        $convs = $this->db->fetchAll('SELECT c.id, u.email as user_email, c.topic_id, c.created_at, c.updated_at FROM conversations c JOIN users u ON c.user_id = u.id ORDER BY c.updated_at DESC');
        require __DIR__ . '/../views/admin/conversations.php';
    }

    public function viewConversation(int $convId): void {
        $this->requireAdmin();
        $messages = $this->db->fetchAll('SELECT role, content, translation, correction FROM messages WHERE conversation_id = ? ORDER BY created_at ASC', [$convId]);
        require __DIR__ . '/../views/admin/conversation_detail.php';
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

    // ------------------- Settings -------------------
    public function settings(): void {
        // Full admin only: this page exposes configuration values (and
        // masked secret values), so read-only viewers must not reach it.
        $this->requireAdmin();
        $this->requireFullAdmin();
        $csrf = $this->generateCsrfToken();
        $config = include __DIR__ . '/../config.php';
        // Variables $csrf and $config are available in the view
        require __DIR__ . '/../views/admin/settings.php';
    }

    public function updateSettings(array $post): void {
        $this->requireAdmin();
        $this->requireFullAdmin();
        if (!$this->validateCsrfToken($post['csrf'] ?? '')) {
            $_SESSION['admin_settings_msg'] = 'Invalid CSRF token — settings were not saved.';
            header('Location: ?page=admin-settings');
            exit;
        }
        // Simple .env update (no validation for brevity).
        // NOTE: PADDLE_WEBHOOK_SECRET is intentionally NOT editable here —
        // the settings page only shows it masked, and managing the secret
        // lives in the deployment's environment variables instead.
        $envPath = __DIR__ . '/../.env';
        $lines   = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $map = [
            'PADDLE_PREMIUM_PLAN_PRICE_ID' => $post['premium_price_id'] ?? '',
            'PADDLE_STARTER_PLAN_PRICE_ID' => $post['starter_price_id'] ?? '',
            'PADDLE_PRO_PLAN_PRICE_ID'     => $post['pro_price_id'] ?? '',
            'PADDLE_STARTER_YEARLY_PRICE_ID' => $post['starter_yearly_price_id'] ?? '',
            'PADDLE_PRO_YEARLY_PRICE_ID'     => $post['pro_yearly_price_id'] ?? '',
            'PADDLE_PREMIUM_YEARLY_PRICE_ID' => $post['premium_yearly_price_id'] ?? '',
        ];
        foreach ($lines as &$line) {
            foreach ($map as $key => $val) {
                if (strpos($line, $key . '=') === 0) {
                    $line = $key . '=' . $val;
                }
            }
        }
        file_put_contents($envPath, implode("\n", $lines) . "\n");
        $_SESSION['admin_settings_msg'] = __('admin.settings_saved');
        header('Location: ?page=admin-settings');
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
