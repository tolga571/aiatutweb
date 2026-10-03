<?php
namespace App\Src;

/**
 * System health + configuration status for the admin panel. Read-only:
 * secrets are only ever reported as set / missing, never shown.
 */
class AdminHealth {
    private Database $db;
    private array $config;

    public function __construct(Database $db, array $config) {
        $this->db = $db;
        $this->config = $config;
    }

    /** What is running: Railway injects the deployed commit at runtime. */
    public function version(): array {
        $sha = (string)getenv('RAILWAY_GIT_COMMIT_SHA');
        return [
            'commit' => $sha !== '' ? substr($sha, 0, 7) : null,
            'commit_message' => (string)getenv('RAILWAY_GIT_COMMIT_MESSAGE') ?: null,
            'branch' => (string)getenv('RAILWAY_GIT_BRANCH') ?: null,
            'environment' => (string)getenv('RAILWAY_ENVIRONMENT_NAME') ?: 'yerel',
            'deployment' => (string)getenv('RAILWAY_DEPLOYMENT_ID') ?: null,
            'php' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
        ];
    }

    public function database(): array {
        $one = fn(string $sql) => $this->db->fetchOne($sql) ?? [];
        $tables = [];
        foreach (['users', 'conversations', 'messages', 'vocabulary_words', 'user_mistakes', 'ai_usage', 'activity_events', 'admin_audit', 'sessions', 'login_attempts'] as $t) {
            $tables[$t] = (int)($one("SELECT COUNT(*) AS c FROM {$t}")['c'] ?? 0);
        }
        return [
            'version' => preg_replace('/ on .*/', '', (string)($one('SELECT version() AS v')['v'] ?? '')),
            'size' => (int)($one('SELECT pg_database_size(current_database()) AS s')['s'] ?? 0),
            'tables' => $tables,
            'active_sessions' => (int)($one('SELECT COUNT(*) AS c FROM sessions WHERE expires_at > NOW()')['c'] ?? 0),
            'admin_sessions' => (int)($one("SELECT COUNT(*) AS c FROM sessions WHERE expires_at > NOW() AND data LIKE '%admin_id|%'")['c'] ?? 0),
        ];
    }

    /** Last delivery per payment webhook + the tail of each webhook log. */
    public function webhooks(): array {
        $out = [];
        foreach (['dodo' => 'dodo_processed_events', 'fastspring' => 'fastspring_processed_events'] as $name => $table) {
            $row = $this->db->fetchOne(
                "SELECT MAX(processed_at) AS last_at,
                        COUNT(*) FILTER (WHERE processed_at >= NOW() - INTERVAL '24 hours') AS day,
                        COUNT(*) FILTER (WHERE processed_at >= NOW() - INTERVAL '7 days') AS week
                 FROM {$table}"
            ) ?? [];
            $out[$name] = $row;
        }
        $paddle = $this->db->fetchOne("SELECT MAX(created_at) AS last_at FROM activity_events WHERE provider = 'paddle'") ?? [];
        $out['paddle'] = ['last_at' => $paddle['last_at'] ?? null, 'day' => null, 'week' => null];
        foreach (['dodo' => 'dodo_webhook.log', 'fastspring' => 'fastspring_webhook.log', 'paddle' => 'paddle_webhook.log'] as $name => $file) {
            $out[$name]['log'] = $this->tail(__DIR__ . '/../data/' . $file, 8);
        }
        return $out;
    }

    public function ai(): array {
        return $this->db->fetchOne(
            "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE NOT ok) AS failed,
                    COUNT(*) FILTER (WHERE ok AND model <> 'gemini-2.5-flash') AS fallback,
                    MAX(created_at) FILTER (WHERE ok) AS last_ok, MAX(created_at) FILTER (WHERE NOT ok) AS last_fail,
                    COALESCE(SUM(cost_usd), 0) AS cost
             FROM ai_usage WHERE created_at >= NOW() - INTERVAL '24 hours'"
        ) ?? [];
    }

    public function security(): array {
        return [
            'attempts' => $this->db->fetchAll(
                "SELECT type, COUNT(*) AS cnt, COUNT(DISTINCT ip) AS ips FROM login_attempts
                 WHERE attempted_at >= NOW() - INTERVAL '24 hours' GROUP BY type ORDER BY cnt DESC"
            ),
            'top_ips' => $this->db->fetchAll(
                "SELECT ip, COUNT(*) AS cnt, MAX(attempted_at) AS last_at, string_agg(DISTINCT type, ', ') AS types
                 FROM login_attempts WHERE attempted_at >= NOW() - INTERVAL '24 hours'
                 GROUP BY ip ORDER BY cnt DESC LIMIT 6"
            ),
            'admin_logins' => $this->db->fetchAll(
                "SELECT admin_email, ip, performed_at, action FROM admin_audit
                 WHERE action IN ('admin_login', 'admin_login_2fa') ORDER BY performed_at DESC LIMIT 6"
            ),
            'admins_without_2fa' => $this->db->fetchAll('SELECT email FROM admins WHERE totp_enabled_at IS NULL ORDER BY id'),
        ];
    }

    /**
     * Configuration status, grouped. Each item: [label, status, value]
     * where status is ok|warn|missing|info. Secrets: value is null.
     */
    public function config(): array {
        $c = $this->config;
        $set = fn($v) => is_string($v) && trim($v) !== '';
        $secret = fn(string $label, $v, string $whenMissing = 'missing') => [$label, $set($v) ? 'ok' : $whenMissing, null];
        $value = fn(string $label, $v, string $status = 'info') => [$label, $status, $set((string)$v) ? (string)$v : '—'];
        $provider = $c['payment_provider'] ?? 'paddle';
        return [
            'Genel' => [
                $value('Aktif ödeme sağlayıcısı', $provider, $provider === 'dodo' ? 'ok' : 'warn'),
                [ 'Veritabanı bağlantısı', $set($c['db_url'] ?? '') ? 'ok' : 'missing', null ],
                $value('Animasyonlar (MOTION_ENABLED)', ($c['motion_enabled'] ?? true) ? 'açık' : 'kapalı'),
            ],
            'Yapay zekâ (Gemini)' => [
                $secret('GEMINI_API_KEY', $c['gemini_api_key'] ?? ''),
                $secret('GEMINI_API_KEY_BACKUP (yedek)', $c['gemini_api_key_backup'] ?? '', 'warn'),
            ],
            'Dodo Payments (canlı)' => [
                $value('Ortam', $c['dodo_environment'] ?? '', ($c['dodo_environment'] ?? '') === 'live' ? 'ok' : 'warn'),
                $secret('DODO_API_KEY', $c['dodo_api_key'] ?? ''),
                $secret('DODO_WEBHOOK_SECRET', $c['dodo_webhook_secret'] ?? ''),
                $value('Starter aylık / yıllık', ($c['dodo_product_paths']['starter']['month'] ?? '') . ' / ' . ($c['dodo_product_paths']['starter']['year'] ?? '')),
                $value('Pro aylık / yıllık', ($c['dodo_product_paths']['pro']['month'] ?? '') . ' / ' . ($c['dodo_product_paths']['pro']['year'] ?? '')),
                $value('Premium aylık / yıllık', ($c['dodo_product_paths']['active']['month'] ?? '') . ' / ' . ($c['dodo_product_paths']['active']['year'] ?? '')),
            ],
            'Paddle (test)' => [
                $value('Ortam', $c['paddle_environment'] ?? '', ($c['paddle_environment'] ?? '') === 'production' ? 'ok' : 'info'),
                $secret('PADDLE_API_KEY', $c['paddle_api_key'] ?? '', 'info'),
                $secret('PADDLE_WEBHOOK_SECRET', $c['paddle_webhook_secret'] ?? '', 'info'),
                $secret('PADDLE_CLIENT_TOKEN', $c['paddle_client_token'] ?? '', 'info'),
            ],
            'FastSpring' => [
                $value('Ortam', $c['fastspring_environment'] ?? ''),
                $secret('FASTSPRING_API_USERNAME', $c['fastspring_api_username'] ?? '', 'info'),
                $secret('FASTSPRING_WEBHOOK_SECRET', $c['fastspring_webhook_secret'] ?? '', 'info'),
            ],
            'E-posta ve giriş' => [
                $secret('MAILTRAP_API_TOKEN (doğrulama / şifre sıfırlama e-postaları)', $c['mailtrap_api_token'] ?? ''),
                $value('Gönderen adres', $c['mail_from_address'] ?? ''),
                $secret('GOOGLE_CLIENT_ID (Google ile giriş)', $c['google_client_id'] ?? '', 'warn'),
            ],
        ];
    }

    private function tail(string $path, int $lines): array {
        if (!is_readable($path)) {
            return [];
        }
        $size = filesize($path);
        $fh = fopen($path, 'r');
        fseek($fh, max(0, $size - 8192));
        $chunk = stream_get_contents($fh);
        fclose($fh);
        $rows = array_values(array_filter(explode("\n", (string)$chunk), fn($l) => trim($l) !== ''));
        return array_slice($rows, -$lines);
    }
}
