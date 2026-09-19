<?php
namespace App\Src;

class AdminController {
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
        $adminId = $_SESSION['admin_id'] ?? null;
        if (!$adminId) {
            header('Location: ?page=admin-login');
            exit;
        }
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
    }

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
        $title = __('admin.login_title');
        $csrf = $this->generateCsrfToken();
        ob_start();
        ?>
        <h2><?= __('admin.login_heading') ?></h2>
        <?php if (!empty($_SESSION['admin_login_error'])): ?>
            <div style="color:#ff6b6b;"> <?= htmlspecialchars($_SESSION['admin_login_error']) ?> </div>
        <?php unset($_SESSION['admin_login_error']); endif; ?>
        <form method="POST" action="?page=admin-login">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label for="email"><?= __('admin.email') ?></label>
            <input type="email" id="email" name="email" required style="width:100%;margin:5px 0;">
            <label for="password"><?= __('admin.password') ?></label>
            <input type="password" id="password" name="password" required style="width:100%;margin:5px 0;">
            <button type="submit" style="background:#28a745;color:#fff;padding:8px 16px;border:none;cursor:pointer;"><?= __('admin.login_btn') ?></button>
        </form>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../views/admin/admin_layout.php';
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
            $_SESSION['admin_login_error'] = 'Invalid CSRF token.';
            header('Location: ?page=admin-login');
            exit;
        }
        if ($this->adminLoginTooManyAttempts($ip)) {
            $_SESSION['admin_login_error'] = 'Too many login attempts. Please try again later.';
            header('Location: ?page=admin-login');
            exit;
        }
        $admin = $this->db->fetchOne('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($admin && password_verify($password, $admin['password'])) {
            $this->clearAdminLoginAttempts($ip);
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: ?page=admin-dashboard');
            exit;
        }
        $this->recordAdminLoginAttempt($ip);
        $_SESSION['admin_login_error'] = 'Invalid email or password.';
        header('Location: ?page=admin-login');
        exit;
    }

    public function logout(): void {
        session_start();
        unset($_SESSION['admin_id']);
        header('Location: ?page=admin-login');
        exit;
    }

    // ------------------- Dashboard -------------------
    public function dashboard(): void {
        $this->requireAdmin();
        // Simple statistics
        $userCount = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM users')['cnt'];
        $paidCount = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM users WHERE has_paid = 1')['cnt'];
        $msgCount  = $this->db->fetchOne('SELECT COUNT(*) as cnt FROM messages')['cnt'];

        // List price per plan, USD — must track the prices actually shown
        // on the pricing page (lang/*.php pricing.*_monthly/_yearly) and the
        // live Dodo product catalog, since that's the current source of
        // truth. This was previously $paidCount * $config['premium_price'],
        // a config key that has never existed, so the dashboard always
        // showed 0 regardless of how many users were actually paying.
        $monthlyPriceUsd = [
            'starter' => ['month' => 15,  'year' => 150 / 12],
            'pro'     => ['month' => 50,  'year' => 500 / 12],
            'active'  => ['month' => 150, 'year' => 1500 / 12],
        ];
        $planCounts = $this->db->fetchAll(
            'SELECT plan_status, billing_interval, COUNT(*) as cnt FROM users WHERE has_paid = 1 GROUP BY plan_status, billing_interval'
        );
        $mrrUsd = 0.0;
        foreach ($planCounts as $row) {
            $interval = ($row['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
            $mrrUsd += ($monthlyPriceUsd[$row['plan_status']][$interval] ?? 0) * (int)$row['cnt'];
        }
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
