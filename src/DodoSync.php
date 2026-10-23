<?php
namespace App\Src;

/**
 * Keeps dodo_live_subscriptions in step with the live Dodo account, so the
 * admin panel's revenue comes from what Dodo actually bills, not from flags
 * in our users table (a leftover subscription id from a test checkout once
 * showed up there as "1 real · $50").
 *
 * Runs on admin page loads, at most every MAX_AGE seconds. When Dodo isn't in
 * live mode or has no key, nothing counts as real. When the API call fails,
 * the last good copy stays and the failure is shown on the dashboard.
 */
class DodoSync {
    public const STATE_KEY = 'dodo_live_sync';
    public const MAX_AGE = 600;
    private const MAX_PAGES = 50;

    private Database $db;
    private array $config;
    private ?DodoClient $client;

    public function __construct(Database $db, array $config, ?DodoClient $client = null) {
        $this->db = $db;
        $this->config = $config;
        $this->client = $client;
    }

    public function syncIfStale(): void {
        $state = $this->state();
        if ($state && time() - (int)($state['at'] ?? 0) < self::MAX_AGE) {
            return;
        }
        $this->sync();
    }

    /** Pulls every active live subscription; returns the new state. */
    public function sync(): array {
        $env = $this->config['dodo_environment'] ?? 'live';
        $client = $this->client ?? new DodoClient($this->config['dodo_api_key'] ?? '', $env);
        if ($env !== 'live' || !$client->isConfigured()) {
            $this->replace([]);
            return $this->save(['ok' => false, 'error' => $env !== 'live' ? 'Dodo is not in live mode' : 'No Dodo API key', 'count' => 0]);
        }
        $items = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $client->listSubscriptions('active', $page);
            if ($batch === null) {
                // Keep the last good copy; only the timestamp and the error change.
                $prev = $this->state() ?? [];
                return $this->save(['ok' => false, 'error' => 'Dodo API request failed', 'count' => (int)($prev['count'] ?? 0)]);
            }
            $items = array_merge($items, $batch);
            if (count($batch) < 100) {
                break;
            }
        }
        $this->replace($items);
        return $this->save(['ok' => true, 'error' => null, 'count' => count($items)]);
    }

    public function state(): ?array {
        $row = $this->db->fetchOne('SELECT value FROM app_state WHERE key = ?', [self::STATE_KEY]);
        $state = $row ? json_decode((string)$row['value'], true) : null;
        return is_array($state) ? $state : null;
    }

    private function replace(array $items): void {
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            $this->db->execute('DELETE FROM dodo_live_subscriptions');
            foreach ($items as $s) {
                if (empty($s['subscription_id'])) {
                    continue;
                }
                $this->db->execute(
                    'INSERT INTO dodo_live_subscriptions (subscription_id, status, product_id, amount_cents, currency, interval_unit, interval_count)
                     VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT (subscription_id) DO NOTHING',
                    [$s['subscription_id'], (string)($s['status'] ?? ''), $s['product_id'] ?? null, (int)($s['recurring_pre_tax_amount'] ?? 0),
                     $s['currency'] ?? null, $s['payment_frequency_interval'] ?? null, max(1, (int)($s['payment_frequency_count'] ?? 1))]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('DodoSync replace failed: ' . $e->getMessage());
        }
    }

    private function save(array $state): array {
        $state['at'] = time();
        // When the last good check happened; kept across failed attempts.
        $state['ok_at'] = $state['ok'] ? $state['at'] : ($this->state()['ok_at'] ?? null);
        $this->db->execute(
            'INSERT INTO app_state (key, value) VALUES (?, ?) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP',
            [self::STATE_KEY, json_encode($state)]
        );
        return $state;
    }
}
