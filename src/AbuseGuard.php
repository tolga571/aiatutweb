<?php
namespace App\Src;

/**
 * Limits that keep Gemini spending bounded and stop free-trial farming.
 * Used by the web routes and the /api/v1 app API alike, so both sides
 * enforce exactly the same rules.
 *
 *  - Sign-ups: at most SIGNUPS_PER_IP_PER_DAY new accounts per IP.
 *  - Free trials: at most TRIALS_PER_IP_PER_30D per IP, none for
 *    throw-away e-mail domains.
 *  - Chat speed: per-user per-minute and per-hour caps; trial messages
 *    also capped per IP per day (many accounts behind one IP).
 *  - Spend: AI_MONTHLY_BUDGET_USD is a hard monthly cap on Gemini cost
 *    (from ai_usage). Trials stop earlier, at AI_TRIAL_BUDGET_SHARE of it,
 *    so paying users keep working the longest. Admins get an e-mail at
 *    50 / 80 / 100 % of the budget, once per month each.
 *
 * Every counter lives in the database, so limits hold across deploys and
 * across the web and the app.
 */
class AbuseGuard {
    public const SIGNUPS_PER_IP_PER_DAY = 3;
    public const TRIALS_PER_IP_PER_30D = 2;
    public const CHAT_PER_MINUTE = 6;
    public const CHAT_PER_HOUR = 120;
    public const TRIAL_CHAT_PER_IP_PER_DAY = 45;
    public const CONTEXT_PER_MINUTE = 20;
    public const CONTEXT_PER_HOUR = 200;
    public const RESEND_PER_HOUR = 5;

    /** Common throw-away mailbox domains; trials from these are refused. */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamailblock.com', 'sharklasers.com',
        '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'tempmailo.com',
        'yopmail.com', 'yopmail.net', 'trashmail.com', 'trashmail.de', 'getnada.com', 'nada.email', 'dispostable.com',
        'maildrop.cc', 'mintemail.com', 'throwawaymail.com', 'fakeinbox.com', 'mohmal.com', 'emailondeck.com',
        'moakt.com', 'mail.tm', 'mailpoof.com', 'spamgourmet.com', 'tempinbox.com', 'burnermail.io',
        'mytemp.email', 'tempr.email', 'discard.email', 'luxusmail.org', 'mailnesia.com', 'tmpmail.org', 'tmail.ws',
    ];

    private Database $db;
    private array $config;
    private ?float $spend = null;

    public function __construct(Database $db, array $config) {
        $this->db = $db;
        $this->config = $config;
    }

    // ── Sign-ups ──────────────────────────────────────────────

    /** True when this IP already created SIGNUPS_PER_IP_PER_DAY accounts in 24 h. */
    public function signupBlocked(string $ip): bool {
        return $this->count('signup', $ip, 86400) >= self::SIGNUPS_PER_IP_PER_DAY;
    }

    public function recordSignup(string $ip): void {
        $this->record('signup', $ip);
    }

    // ── Free trial ────────────────────────────────────────────

    /**
     * Why this user can't start a free trial, or null when they can.
     * 'disposable_email' | 'trial_limit_ip'
     */
    public function trialRefusal(array $user, string $ip): ?string {
        $domain = strtolower(substr(strrchr((string)($user['email'] ?? ''), '@') ?: '', 1));
        if ($domain !== '' && $this->isDisposable($domain)) {
            return 'disposable_email';
        }
        $n = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM trial_grants WHERE ip = ? AND user_id != ? AND created_at > CURRENT_TIMESTAMP - INTERVAL '30 days'",
            [$ip, (int)$user['id']]
        )['c'] ?? 0);
        return $n >= self::TRIALS_PER_IP_PER_30D ? 'trial_limit_ip' : null;
    }

    /**
     * Starts the trial when allowed (inactive → trial). Returns null on
     * success or the refusal code.
     */
    public function startTrial(array $user, string $ip): ?string {
        if (($user['plan_status'] ?? 'inactive') !== 'inactive') {
            return null; // already trial or paid: nothing to do
        }
        if ($why = $this->trialRefusal($user, $ip)) {
            return $why;
        }
        $this->db->execute("UPDATE users SET plan_status = 'trial' WHERE id = ? AND COALESCE(plan_status, 'inactive') = 'inactive'", [(int)$user['id']]);
        $this->db->insertIgnore('trial_grants', ['user_id', 'ip'], [(int)$user['id'], $ip]);
        return null;
    }

    public function isDisposable(string $domain): bool {
        foreach (self::DISPOSABLE_DOMAINS as $d) {
            if ($domain === $d || str_ends_with($domain, '.' . $d)) {
                return true;
            }
        }
        return false;
    }

    // ── Chat ──────────────────────────────────────────────────

    /**
     * Why this message must not reach Gemini, or null when it may.
     * 'rate_minute' | 'rate_hour' | 'trial_ip_daily' | 'trial_paused' | 'ai_paused'
     */
    public function chatRefusal(int $userId, string $ip, bool $isTrial): ?string {
        $budget = $this->budget();
        if ($budget > 0) {
            $spend = $this->monthlySpend();
            if ($spend >= $budget) {
                return 'ai_paused';
            }
            if ($isTrial && $spend >= $budget * $this->trialShare()) {
                return 'trial_paused';
            }
        }
        $key = 'u:' . $userId;
        if ($this->count('chat', $key, 60) >= self::CHAT_PER_MINUTE) {
            return 'rate_minute';
        }
        if ($this->count('chat', $key, 3600) >= self::CHAT_PER_HOUR) {
            return 'rate_hour';
        }
        if ($isTrial && $this->count('trial-chat', $ip, 86400) >= self::TRIAL_CHAT_PER_IP_PER_DAY) {
            return 'trial_ip_daily';
        }
        return null;
    }

    /** Counts one chat message (call before asking Gemini, so bursts can't slip through). */
    public function recordChat(int $userId, string $ip, bool $isTrial): void {
        $this->record('chat', 'u:' . $userId);
        if ($isTrial) {
            $this->record('trial-chat', $ip);
        }
    }

    // ── Word-bank example sentences, verification e-mails ─────

    /**
     * Why a new example sentence must not be generated, or null when it may
     * (cached ones are always served). 'ai_paused' | 'rate_minute' | 'rate_hour'
     */
    public function contextRefusal(int $userId): ?string {
        $budget = $this->budget();
        if ($budget > 0 && $this->monthlySpend() >= $budget) {
            return 'ai_paused';
        }
        $key = 'u:' . $userId;
        if ($this->count('word-context', $key, 60) >= self::CONTEXT_PER_MINUTE) {
            return 'rate_minute';
        }
        if ($this->count('word-context', $key, 3600) >= self::CONTEXT_PER_HOUR) {
            return 'rate_hour';
        }
        return null;
    }

    public function recordContext(int $userId): void {
        $this->record('word-context', 'u:' . $userId);
    }

    /** True when this IP or address asked for too many verification e-mails; counts the request. */
    public function resendBlocked(string $ip, string $email): bool {
        $mailKey = 'm:' . hash('sha256', mb_strtolower(trim($email)));
        if ($this->count('resend-verify', $ip, 3600) >= self::RESEND_PER_HOUR * 2
            || $this->count('resend-verify', $mailKey, 3600) >= self::RESEND_PER_HOUR) {
            return true;
        }
        $this->record('resend-verify', $ip);
        $this->record('resend-verify', $mailKey);
        return false;
    }

    // ── Spend ─────────────────────────────────────────────────

    /** Monthly Gemini budget in USD; 0 turns the cap off. */
    public function budget(): float {
        return max(0.0, (float)($this->config['ai_monthly_budget_usd'] ?? 0));
    }

    private function trialShare(): float {
        $s = (float)($this->config['ai_trial_budget_share'] ?? 0.3);
        return $s > 0 && $s <= 1 ? $s : 0.3;
    }

    /** Gemini cost logged this calendar month (UTC), from ai_usage. */
    public function monthlySpend(): float {
        if ($this->spend === null) {
            $this->spend = (float)($this->db->fetchOne(
                "SELECT COALESCE(SUM(cost_usd), 0) AS s FROM ai_usage WHERE created_at >= date_trunc('month', CURRENT_TIMESTAMP)"
            )['s'] ?? 0);
        }
        return $this->spend;
    }

    /**
     * E-mails every admin once per month when spend crosses 50, 80 and
     * 100 % of the budget. Cheap enough to call after each AI reply.
     */
    public function checkBudgetAlerts(): void {
        $budget = $this->budget();
        if ($budget <= 0) {
            return;
        }
        $this->spend = null;
        $spend = $this->monthlySpend();
        $month = gmdate('Y-m');
        foreach ([100, 80, 50] as $pct) {
            if ($spend < $budget * $pct / 100) {
                continue;
            }
            $flag = "ai_budget_alert_{$month}_{$pct}";
            if ($this->db->fetchOne('SELECT 1 AS x FROM app_state WHERE key = ?', [$flag])) {
                return; // this (and every lower) threshold was already announced
            }
            $this->db->insertIgnore('app_state', ['key', 'value'], [$flag, (string)round($spend, 2)]);
            $subject = sprintf('Jumplearner: AI spend at %d%% of the monthly budget ($%.2f / $%.2f)', $pct, $spend, $budget);
            $body = sprintf(
                '<p>Gemini spend this month is <b>$%.2f</b> of the <b>$%.2f</b> budget (%d%%).</p>'
                . '<p>%s</p><p>Change the cap with the AI_MONTHLY_BUDGET_USD variable on Railway. Details: admin → AI usage &amp; cost.</p>',
                $spend, $budget, $pct,
                $pct >= 100 ? 'The AI tutor is now <b>paused for everyone</b> until the 1st of next month or until the budget is raised.'
                    : ($spend >= $budget * $this->trialShare() ? 'Free-trial chats are paused; paying users still work.' : 'Everything still works.')
            );
            $mailer = new Mailer($this->config);
            foreach ($this->db->fetchAll('SELECT email FROM admins') as $a) {
                $mailer->send($a['email'], $subject, $body);
            }
            error_log($subject);
            return;
        }
    }

    // ── Counters (login_attempts keeps 24 h of rows) ──────────

    private function count(string $type, string $key, int $seconds): int {
        return (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND type = ? AND attempted_at > CURRENT_TIMESTAMP - INTERVAL '" . (int)$seconds . " seconds'",
            [$key, $type]
        )['c'] ?? 0);
    }

    private function record(string $type, string $key): void {
        $this->db->execute('INSERT INTO login_attempts (ip, type) VALUES (?, ?)', [$key, $type]);
    }
}
