<?php
namespace App\Src;

/**
 * Per-request Gemini token/cost log (ai_usage table) and the queries behind
 * the admin "AI usage" page. Google's own billing only shows a daily total;
 * this ties every request to a user, plan and model, in real time.
 */
class AiUsage {
    /**
     * USD per 1M tokens, Gemini API paid tier (ai.google.dev/gemini-api/docs/pricing,
     * checked 2026-09-30). Thinking tokens bill at the output rate. Update
     * when Google changes prices — old rows keep the cost they were logged with.
     */
    public const PRICES = [
        'gemini-2.5-flash'      => ['in' => 0.30, 'out' => 2.50],
        'gemini-2.5-flash-lite' => ['in' => 0.10, 'out' => 0.40],
        'gemini-2.5-pro'        => ['in' => 1.25, 'out' => 10.00],
    ];

    public static function cost(string $model, int $promptTokens, int $outputTokens): float {
        $p = self::PRICES[$model] ?? self::PRICES['gemini-2.5-flash'];
        return ($promptTokens * $p['in'] + $outputTokens * $p['out']) / 1_000_000;
    }

    /**
     * Logs one request. $usage is GeminiClient::getLastUsage() (null when the
     * call failed before any model answered). Never throws — cost logging
     * must not break a chat reply.
     */
    public static function record(Database $db, ?int $userId, ?array $usage, bool $ok, string $feature = 'chat'): void {
        try {
            $model = $usage['model'] ?? null;
            $prompt = (int)($usage['prompt_tokens'] ?? 0);
            $output = (int)($usage['output_tokens'] ?? 0);
            $thoughts = (int)($usage['thought_tokens'] ?? 0);
            $db->execute(
                'INSERT INTO ai_usage (user_id, feature, model, ok, prompt_tokens, output_tokens, thought_tokens, cost_usd) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $feature, $model, $ok ? 'true' : 'false', $prompt, $output, $thoughts,
                 $model ? round(self::cost($model, $prompt, $output + $thoughts), 6) : 0]
            );
        } catch (\Throwable $e) {
            error_log('AiUsage::record failed: ' . $e->getMessage());
        }
    }

    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /** Totals since $since (a SQL timestamp expression built only from constants below). */
    public function totals(string $since): array {
        return $this->db->fetchOne(
            "SELECT COUNT(*) AS requests,
                    COUNT(*) FILTER (WHERE NOT ok) AS failed,
                    COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                    COALESCE(SUM(output_tokens + thought_tokens), 0) AS output_tokens,
                    COALESCE(SUM(cost_usd), 0) AS cost
             FROM ai_usage WHERE created_at >= {$since}"
        ) ?? [];
    }

    public function byModel(string $since): array {
        return $this->db->fetchAll(
            "SELECT COALESCE(model, '(failed)') AS model, COUNT(*) AS requests,
                    SUM(prompt_tokens) AS prompt_tokens, SUM(output_tokens + thought_tokens) AS output_tokens,
                    SUM(cost_usd) AS cost
             FROM ai_usage WHERE created_at >= {$since}
             GROUP BY 1 ORDER BY cost DESC"
        );
    }

    /** AI cost this period per plan, next to that plan's paying users. */
    public function byPlan(string $since): array {
        return $this->db->fetchAll(
            "SELECT u.plan_status AS plan, COALESCE(u.billing_interval, 'month') AS billing_interval,
                    COUNT(DISTINCT u.id) FILTER (WHERE u.has_paid = 1) AS paying_users,
                    COUNT(a.id) AS requests, COALESCE(SUM(a.cost_usd), 0) AS cost
             FROM users u LEFT JOIN ai_usage a ON a.user_id = u.id AND a.created_at >= {$since}
             WHERE u.plan_status <> 'inactive' OR a.id IS NOT NULL
             GROUP BY 1, 2 ORDER BY 1, 2"
        );
    }

    public function topUsers(string $since, int $limit = 15): array {
        return $this->db->fetchAll(
            "SELECT u.id, u.email, u.plan_status, COUNT(a.id) AS requests, SUM(a.cost_usd) AS cost
             FROM ai_usage a JOIN users u ON u.id = a.user_id
             WHERE a.created_at >= {$since}
             GROUP BY u.id, u.email, u.plan_status ORDER BY cost DESC LIMIT " . (int)$limit
        );
    }

    public function daily(int $days = 14): array {
        return $this->db->fetchAll(
            "SELECT date(created_at) AS day, COUNT(*) AS requests, COUNT(*) FILTER (WHERE NOT ok) AS failed,
                    COALESCE(SUM(cost_usd), 0) AS cost
             FROM ai_usage WHERE created_at >= CURRENT_DATE - INTERVAL '" . (int)$days . " days'
             GROUP BY 1 ORDER BY 1 DESC"
        );
    }
}
