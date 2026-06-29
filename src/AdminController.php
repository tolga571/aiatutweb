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

    public function handleLogin(array $post): void {
        $email    = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';
        $csrf     = $post['csrf'] ?? '';
        if (!$this->validateCsrfToken($csrf)) {
            $_SESSION['admin_login_error'] = 'Invalid CSRF token.';
            header('Location: ?page=admin-login');
            exit;
        }
        $admin = $this->db->fetchOne('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: ?page=admin-dashboard');
            exit;
        }
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
        $revenue   = $paidCount * ($this->config['premium_price'] ?? 0);
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    // ------------------- Users -------------------
    public function listUsers(): void {
        $this->requireAdmin();
        $users = $this->db->fetchAll('SELECT id, email, name, xp, has_paid, plan_status FROM users');
        require __DIR__ . '/../views/admin/users.php';
    }

    // ------------------- Admins -------------------
    public function listAdmins(): void {
        $this->requireAdmin();
        $admins = $this->db->fetchAll('SELECT id, email, name, created_at FROM admins');
        require __DIR__ . '/../views/admin/admins.php';
    }

    // ------------------- Payments -------------------
    public function listPayments(): void {
        $this->requireAdmin();
        $payments = $this->db->fetchAll('SELECT u.id, u.email, u.plan_status, u.has_paid, u.created_at FROM users u WHERE u.has_paid = 1');
        require __DIR__ . '/../views/admin/payments.php';
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
    // ------------------- Settings -------------------
    public function settings(): void {
        $this->requireAdmin();
        $csrf = $this->generateCsrfToken();
        $config = include __DIR__ . '/../config.php';
        // Variables $csrf and $config are available in the view
        require __DIR__ . '/../views/admin/settings.php';
    }

    public function updateSettings(array $post): void {
        $this->requireAdmin();
        // Simple .env update (no validation for brevity)
        $envPath = __DIR__ . '/../.env';
        $lines   = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $map = [
            'PADDLE_PREMIUM_PLAN_PRICE_ID' => $post['premium_price_id'] ?? '',
            'PADDLE_STARTER_PLAN_PRICE_ID' => $post['starter_price_id'] ?? '',
            'PADDLE_PRO_PLAN_PRICE_ID'     => $post['pro_price_id'] ?? '',
            'PADDLE_WEBHOOK_SECRET'       => $post['webhook_secret'] ?? '',
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
        }
        fclose($output);
        exit;
    }
}
?>
