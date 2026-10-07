<?php
namespace App\Src\Api;

use App\Src\AbuseGuard;
use App\Src\Account;
use App\Src\Auth;
use App\Src\Chat;
use App\Src\Database;
use App\Src\CardLists;
use App\Src\Flashcard;
use App\Src\GeminiClient;
use App\Src\Language;
use App\Src\Mailer;
use App\Src\Mistakes;
use App\Src\TokenManager;

/**
 * JSON API for the mobile app: /api/v1/...
 *
 * Every response is {"ok": true, "data": ...} or
 * {"ok": false, "error": {"code": "...", "message": "..."}}.
 * Client errors use 4xx; anything unexpected is HTTP 200 + ok:false with
 * code "server_error", never a 5xx — Cloudflare replaces 5xx bodies with
 * its own page and the app would lose the error.
 *
 * Auth: "Authorization: Bearer jl_..." from /auth/login, /auth/register or
 * /auth/google (see Tokens). No session, no CSRF (nothing is cookie-based).
 * Language: the app sends "X-App-Lang: tr"; messages come back in it.
 *
 * The business logic is the same classes the website uses (Auth, Chat,
 * Flashcard, Mistakes, Account, TokenManager) — keep it that way so web and
 * app stay in step. API changes must stay backward compatible: old app
 * versions stay installed for months.
 */
class Router
{
    public const VERSION = 1;
    public const TRIAL_MESSAGE_LIMIT = 15;

    private Database $db;
    private array $config;
    private Auth $auth;
    private Tokens $tokens;
    private ?array $user = null;
    private string $bearer = '';
    private array $input = [];

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->auth = new Auth($db, false);
        $this->tokens = new Tokens($db);
    }

    // ── Entry point ───────────────────────────────────────────

    public function handle(string $method, string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        // Token auth, no cookies: any origin may call it (the Expo web
        // preview runs on another origin).
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, If-None-Match, X-App-Lang, X-App-Version, X-App-Platform');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            $this->input = $this->readInput();
            $this->bearer = $this->readBearer();
            if ($this->bearer !== '') {
                $this->user = $this->tokens->user($this->bearer);
            }
            $this->loadLanguage();
            $this->dispatch($method, trim($path, '/'));
        } catch (ApiError $e) {
            $this->fail($e->errorCode, $e->getMessage(), $e->status, $e->extra);
        } catch (\Throwable $e) {
            error_log('API ' . $method . ' ' . $path . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->fail('server_error', t('api.server_error'), 200);
        }
    }

    private function dispatch(string $method, string $path): void
    {
        // [method, path regex, handler, access: public | user | plan]
        $routes = [
            ['GET', 'config', 'appConfig', 'public'],
            ['GET', 'i18n/([a-z]{2,3})', 'i18n', 'public'],

            ['POST', 'auth/login', 'login', 'public'],
            ['POST', 'auth/register', 'register', 'public'],
            ['POST', 'auth/google', 'google', 'public'],
            ['POST', 'auth/resend-verification', 'resendVerification', 'public'],
            ['POST', 'auth/forgot-password', 'forgotPassword', 'public'],
            ['POST', 'auth/logout', 'logout', 'user'],

            ['GET', 'me', 'me', 'user'],
            ['PATCH', 'me', 'updateMe', 'user'],
            ['POST', 'onboarding', 'onboarding', 'user'],
            ['POST', 'trial/start', 'startTrial', 'user'],
            ['GET', 'dashboard', 'dashboard', 'plan'],

            ['GET', 'topics', 'topics', 'plan'],
            ['GET', 'conversations', 'conversations', 'plan'],
            ['GET', 'conversations/(\d+)', 'conversation', 'plan'],
            ['POST', 'chat', 'chat', 'plan'],
            ['POST', 'messages/(\d+)/report', 'reportMessage', 'user'],

            ['GET', 'flashcards/stats', 'flashcardStats', 'plan'],
            ['GET', 'flashcards/categories', 'flashcardCategories', 'plan'],
            ['GET', 'flashcards/languages', 'flashcardLanguages', 'plan'],
            ['GET', 'flashcards', 'flashcards', 'plan'],
            ['POST', 'flashcards', 'flashcardCreate', 'plan'],
            ['POST', 'flashcards/review', 'flashcardReview', 'plan'],
            ['GET', 'flashcards/packs', 'flashcardPacks', 'plan'],
            ['POST', 'flashcards/packs', 'flashcardImportPack', 'plan'],
            ['GET', 'flashcards/(\d+)', 'flashcard', 'plan'],
            ['PATCH', 'flashcards/(\d+)', 'flashcardUpdate', 'plan'],
            ['DELETE', 'flashcards/(\d+)', 'flashcardDelete', 'plan'],
            ['POST', 'flashcards/(\d+)/favorite', 'flashcardFavorite', 'plan'],
            ['POST', 'flashcards/(\d+)/learned', 'flashcardLearned', 'plan'],

            ['GET', 'words', 'words', 'plan'],
            ['POST', 'words', 'wordAdd', 'plan'],

            ['GET', 'lists', 'lists', 'plan'],
            ['POST', 'lists', 'listCreate', 'plan'],
            ['PATCH', 'lists/(\d+)', 'listRename', 'plan'],
            ['DELETE', 'lists/(\d+)', 'listDelete', 'plan'],
            ['GET', 'lists/(\d+)/cards', 'listCards', 'plan'],
            ['POST', 'lists/(\d+)/cards', 'listAdd', 'plan'],
            ['POST', 'lists/(\d+)/cards/remove', 'listRemove', 'plan'],

            ['GET', 'mistakes', 'mistakes', 'plan'],
            ['POST', 'mistakes/(\d+)/review', 'mistakeReview', 'plan'],

            ['GET', 'alphabet', 'alphabet', 'public'],
            ['POST', 'alphabet/toggle', 'alphabetToggle', 'user'],

            ['GET', 'account/export', 'accountExport', 'user'],
            ['DELETE', 'account', 'accountDelete', 'user'],
        ];
        $pathMatched = false;
        foreach ($routes as [$m, $pattern, $handler, $access]) {
            if (!preg_match('#^' . $pattern . '$#', $path, $args)) {
                continue;
            }
            $pathMatched = true;
            if ($m !== $method) {
                continue;
            }
            if ($access !== 'public' && !$this->user) {
                throw new ApiError('unauthorized', t('api.unauthorized'), 401);
            }
            if ($access === 'plan' && !$this->hasPlan()) {
                throw new ApiError('plan_required', t('api.plan_required'), 403);
            }
            array_shift($args);
            $this->$handler(...$args);
            return;
        }
        throw $pathMatched
            ? new ApiError('method_not_allowed', 'Method not allowed', 405)
            : new ApiError('not_found', t('api.not_found'), 404);
    }

    // ── Public ────────────────────────────────────────────────

    /** Everything the app needs before sign-in: versions, languages, form options, feature switches. */
    private function appConfig(): void
    {
        $lang = fn(string $code) => [
            'code' => $code,
            'name' => Language::langName($code),
            'native_name' => Language::nativeName($code),
            'flag' => Language::flagCountry($code),
            'dir' => Language::dir($code),
            'speech_locale' => Language::speechLocale($code),
        ];
        $this->ok([
            'api_version' => self::VERSION,
            'min_app_version' => (string)($this->config['mobile_min_version'] ?? '1.0.0'),
            'languages' => [
                'ui' => array_map($lang, Language::listed('ui')),
                'learn' => array_map($lang, Language::listed('learn')),
            ],
            'cefr_levels' => Auth::CEFR_LEVELS,
            'learning_goals' => Auth::LEARNING_GOALS,
            'interest_areas' => Auth::INTEREST_AREAS,
            'trial_message_limit' => self::TRIAL_MESSAGE_LIMIT,
            'features' => [
                // Google Play Billing arrives in a later app version; until
                // then the app shows no purchase screen at all.
                'in_app_purchase' => false,
                'google_sign_in' => !empty($this->config['google_mobile_client_ids']),
            ],
            'links' => [
                'privacy' => 'https://jumplearner.com/privacy-policy',
                'terms' => 'https://jumplearner.com/terms-and-conditions',
                'account_deletion' => 'https://jumplearner.com/?page=account-delete-confirm',
            ],
            'support_email' => 'info@jumplearner.com',
        ]);
    }

    /** UI strings for one language (English filled in where missing). Admin strings are left out. */
    private function i18n(string $code): void
    {
        if (!Language::isUsable($code, 'ui')) {
            throw new ApiError('not_found', t('api.not_found'), 404);
        }
        $strings = Language::strings($code) + Language::strings(Language::DEFAULT);
        $strings = array_filter($strings, fn($k) => !str_starts_with($k, 'admin.'), ARRAY_FILTER_USE_KEY);
        ksort($strings);
        $version = substr(md5(json_encode($strings)), 0, 12);
        header('ETag: "' . $version . '"');
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"') === $version) {
            http_response_code(304);
            exit;
        }
        $this->ok(['lang' => $code, 'dir' => Language::dir($code), 'version' => $version, 'strings' => $strings]);
    }

    // ── Auth ──────────────────────────────────────────────────

    private function login(): void
    {
        $ip = client_ip();
        if ($this->auth->tooManyAttempts($ip, 'login', 8, 900)) {
            throw new ApiError('rate_limited', t('auth.error_too_many_attempts'), 429);
        }
        $user = $this->auth->verifyCredentials(trim($this->str('email')), $this->str('password'));
        if (!$user && $this->auth->lastErrorCode === 'unverified') {
            throw new ApiError('email_unverified', $this->auth->lastError, 403);
        }
        if (!$user) {
            $this->auth->recordAttempt($ip, 'login');
            throw new ApiError('invalid_credentials', $this->auth->lastError ?: t('auth.invalid_credentials'), 401);
        }
        $this->auth->clearAttempts($ip, 'login');
        $this->auth->recordActivity($user);
        $this->signedIn((int)$user['id']);
    }

    
    private function resendVerification(): void
    {
        $email = trim($this->str('email'));
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ? AND email_verified_at IS NULL AND google_id IS NULL', [$email]);
        if ($user) {
            $verifyToken = $this->auth->createEmailVerificationToken((int)$user['id']);
            $verifyUrl = 'https://jumplearner.com/?page=verify-email&token=' . urlencode($verifyToken);
            (new Mailer($this->config))->send(
                $email,
                t('auth.verify_email_subject'),
                '<p>' . t('auth.verify_email_body') . '</p><p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>'
            );
        }
        $this->ok(['sent' => true]);
    }

    private function register(): void
    {
        $ip = client_ip();
        $abuse = new AbuseGuard($this->db, $this->config);
        if ($this->auth->tooManyAttempts($ip, 'register', 5, 3600) || $abuse->signupBlocked($ip)) {
            throw new ApiError('rate_limited', t('auth.error_too_many_attempts'), 429);
        }
        $this->auth->recordAttempt($ip, 'register');
        $email = trim($this->str('email'));
        $password = $this->str('password');
        $name = trim($this->str('name'));
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('auth.error_invalid_email');
        }
        if (mb_strlen($name) < 2) {
            $errors['name'] = t('auth.error_invalid_name');
        }
        if (strlen($password) < 8) {
            $errors['password'] = t('auth.error_weak_password');
        }
        if (empty($this->input['accept_terms'])) {
            $errors['accept_terms'] = t('auth.error_terms');
        }
        if ($errors) {
            throw new ApiError('validation', reset($errors), 422, ['fields' => $errors]);
        }
        if (!$this->auth->register($email, $password, $name)) {
            throw new ApiError('registration_failed', $this->auth->lastError ?: t('auth.registration_failed'), 422);
        }
        $this->auth->clearAttempts($ip, 'register');
        $abuse->recordSignup($ip);
        $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        $verifyToken = $this->auth->createEmailVerificationToken((int)$user['id']);
        $verifyUrl = 'https://jumplearner.com/?page=verify-email&token=' . urlencode($verifyToken);
        (new Mailer($this->config))->send(
            $email,
            t('auth.verify_email_subject'),
            '<p>' . t('auth.verify_email_body') . '</p><p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>'
        );
        // Like the website: no session until the address is confirmed.
        $this->ok(['awaiting_verification' => true, 'email' => $email], 201);
    }

    /** Sign in / up with a Google ID token obtained by the app's native Google Sign-In. */
    private function google(): void
    {
        $audiences = array_merge([(string)($this->config['google_client_id'] ?? '')], $this->config['google_mobile_client_ids'] ?? []);
        $claims = Auth::verifyGoogleIdToken($this->str('id_token'), $audiences);
        if (!$claims) {
            throw new ApiError('invalid_credentials', t('auth.invalid_credentials'), 401);
        }
        $abuse = new AbuseGuard($this->db, $this->config);
        [$user, $created] = $this->auth->findOrCreateGoogleUser($claims, !$abuse->signupBlocked(client_ip()));
        if ($created) {
            $abuse->recordSignup(client_ip());
        }
        if (!$user) {
            throw new ApiError('too_many_attempts', t('auth.error_too_many_attempts'), 429);
        }
        if (!empty($user['suspended_at'])) {
            throw new ApiError('account_suspended', t('auth.account_suspended'), 403);
        }
        if (empty($user['email_verified_at'])) {
            // Google already verified this address.
            $this->db->execute('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = ? AND email_verified_at IS NULL', [$user['id']]);
        }
        $this->auth->recordActivity($user);
        $this->signedIn((int)$user['id'], $created ? 201 : 200);
    }

    /** Always "ok", whether or not the email has an account (no account enumeration). */
    private function forgotPassword(): void
    {
        $ip = client_ip();
        if ($this->auth->tooManyAttempts($ip, 'password-reset', 5, 3600)) {
            throw new ApiError('rate_limited', t('auth.error_too_many_attempts'), 429);
        }
        $this->auth->recordAttempt($ip, 'password-reset');
        $email = trim($this->str('email'));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]) : null;
        if ($user) {
            $token = $this->auth->createPasswordResetToken((int)$user['id']);
            $url = 'https://jumplearner.com/?page=reset-password&token=' . urlencode($token);
            (new Mailer($this->config))->send(
                $email,
                t('auth.reset_email_subject'),
                '<p>' . t('auth.reset_email_body') . '</p><p><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></p>'
            );
        }
        $this->ok(['sent' => true]);
    }

    private function logout(): void
    {
        $this->tokens->revoke($this->bearer);
        $this->ok(['signed_out' => true]);
    }

    private function signedIn(int $userId, int $status = 200): void
    {
        $token = $this->tokens->issue(
            $userId,
            $this->str('device'),
            (string)($_SERVER['HTTP_X_APP_PLATFORM'] ?? ''),
            (string)($_SERVER['HTTP_X_APP_VERSION'] ?? '')
        );
        $this->user = $this->freshUser($userId);
        $this->ok(['token' => $token, 'user' => $this->userPayload($this->user)], $status);
    }

    // ── Profile ───────────────────────────────────────────────

    private function me(): void
    {
        $this->ok(['user' => $this->userPayload($this->user)]);
    }

    private function updateMe(): void
    {
        $u = $this->user;
        $set = [];
        $langs = Language::supportedLangs();
        $native = $this->input['native_lang'] ?? $u['native_lang'];
        $target = $this->input['target_lang'] ?? $u['target_lang'];
        foreach (['native_lang' => $native, 'target_lang' => $target] as $field => $value) {
            if (array_key_exists($field, $this->input)) {
                if (!in_array($value, $langs, true)) {
                    throw new ApiError('validation', t('api.invalid_value'), 422, ['fields' => [$field => t('api.invalid_value')]]);
                }
                $set[$field] = $value;
            }
        }
        if ($native === $target) {
            throw new ApiError('validation', t('onboarding.same_lang_error'), 422, ['fields' => ['target_lang' => t('onboarding.same_lang_error')]]);
        }
        $choices = ['cefr_level' => Auth::CEFR_LEVELS, 'learning_goal' => Auth::LEARNING_GOALS, 'interest_area' => Auth::INTEREST_AREAS];
        foreach ($choices as $field => $allowed) {
            if (array_key_exists($field, $this->input)) {
                if (!in_array($this->input[$field], $allowed, true)) {
                    throw new ApiError('validation', t('api.invalid_value'), 422, ['fields' => [$field => t('api.invalid_value')]]);
                }
                $set[$field] = $this->input[$field];
            }
        }
        if (array_key_exists('ui_lang', $this->input)) {
            $ui = $this->input['ui_lang'];
            if ($ui !== null && !Language::isUsable((string)$ui, 'ui')) {
                throw new ApiError('validation', t('api.invalid_value'), 422, ['fields' => ['ui_lang' => t('api.invalid_value')]]);
            }
            $set['ui_lang'] = $ui;
        }
        if (array_key_exists('name', $this->input)) {
            $name = trim((string)$this->input['name']);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
                throw new ApiError('validation', t('auth.error_invalid_name'), 422, ['fields' => ['name' => t('auth.error_invalid_name')]]);
            }
            $set['name'] = $name;
        }
        if ($set) {
            $cols = implode(', ', array_map(fn($c) => "$c = ?", array_keys($set)));
            $this->db->execute("UPDATE users SET $cols WHERE id = ?", array_merge(array_values($set), [$u['id']]));
        }
        $this->user = $this->freshUser((int)$u['id']);
        $this->ok(['user' => $this->userPayload($this->user)]);
    }

    /** Same as the web onboarding form, then starts the free trial (the web's next step). */
    private function onboarding(): void
    {
        $langs = Language::supportedLangs();
        $native = Auth::pick($this->input['native_lang'] ?? null, $langs, 'en');
        $target = Auth::pick($this->input['target_lang'] ?? null, $langs, 'en');
        if ($native === $target) {
            throw new ApiError('validation', t('onboarding.same_lang_error'), 422, ['fields' => ['target_lang' => t('onboarding.same_lang_error')]]);
        }
        $this->auth->saveOnboarding(
            (int)$this->user['id'], $native, $target,
            (string)($this->input['cefr_level'] ?? 'A1'),
            (string)($this->input['learning_goal'] ?? 'conversation'),
            (string)($this->input['interest_area'] ?? 'general')
        );
        $this->startTrial();
    }

    private function startTrial(): void
    {
        $refusal = (new AbuseGuard($this->db, $this->config))->startTrial($this->user, client_ip());
        if ($refusal) {
            throw new ApiError($refusal, t('error.' . $refusal), 403);
        }
        $this->user = $this->freshUser((int)$this->user['id']);
        $this->ok(['user' => $this->userPayload($this->user)]);
    }

    private function dashboard(): void
    {
        $uid = (int)$this->user['id'];
        $lang = $this->user['target_lang'] ?? 'en';
        try {
            (new Mistakes($this->db))->sync($uid, $lang);
        } catch (\Throwable $e) {
            error_log('API dashboard: mistakes sync failed: ' . $e->getMessage());
        }
        $count = fn(string $sql, array $args) => (int)($this->db->fetchOne($sql, $args)['c'] ?? 0);
        $this->ok([
            'user' => $this->userPayload($this->user),
            'stats' => [
                'conversations' => $count('SELECT COUNT(*) AS c FROM conversations WHERE user_id = ?', [$uid]),
                'messages' => $count('SELECT COUNT(*) AS c FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE user_id = ?)', [$uid]),
                'words' => $count('SELECT COUNT(*) AS c FROM vocabulary_words WHERE user_id = ?', [$uid]),
                'words_today' => $count('SELECT COUNT(*) AS c FROM vocabulary_words WHERE user_id = ? AND date(created_at) = CURRENT_DATE', [$uid]),
                'cards_due' => $count('SELECT COUNT(*) AS c FROM user_flashcards WHERE user_id = ? AND learned_at IS NULL AND next_review <= CURRENT_TIMESTAMP', [$uid]),
                'cards_mastered' => $count("SELECT COUNT(*) AS c FROM user_flashcards WHERE user_id = ? AND status = 'mastered'", [$uid]),
                'mistakes_open' => $count('SELECT COUNT(*) AS c FROM user_mistakes WHERE user_id = ? AND language = ? AND learned_at IS NULL', [$uid, $lang]),
            ],
            'recent_conversations' => $this->db->fetchAll(
                'SELECT id, updated_at, (SELECT content FROM messages WHERE conversation_id = conversations.id ORDER BY created_at ASC LIMIT 1) AS title
                 FROM conversations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5',
                [$uid]
            ),
            'tip' => t('dash.tip_' . ((int)date('z') % 5 + 1)),
        ]);
    }

    // ── Chat ──────────────────────────────────────────────────

    private function topics(): void
    {
        $topics = (new Chat($this->db, $this->config))->getTopics($this->user['interest_area'] ?? null);
        $out = [];
        foreach ($topics as $id => $t) {
            $out[] = ['id' => $id, 'label' => $t['title'], 'description' => $t['description']];
        }
        $this->ok(['topics' => $out]);
    }

    private function conversations(): void
    {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
        $this->ok(['conversations' => (new Chat($this->db, $this->config))->getConversations((int)$this->user['id'], $limit)]);
    }

    private function conversation(string $id): void
    {
        $chat = new Chat($this->db, $this->config);
        $messages = $chat->getMessages((int)$this->user['id'], (int)$id);
        if (!$messages && !$this->db->fetchOne('SELECT id FROM conversations WHERE id = ? AND user_id = ?', [(int)$id, $this->user['id']])) {
            throw new ApiError('not_found', t('api.not_found'), 404);
        }
        foreach ($messages as &$m) {
            unset($m['metadata']);
        }
        $this->ok(['conversation_id' => (int)$id, 'messages' => $messages]);
    }

    /** One message to Kai. Same checks and result as the web chat. */
    private function chat(): void
    {
        $message = trim($this->str('message'));
        if ($message === '') {
            throw new ApiError('validation', t('api.empty_message'), 422);
        }
        $uid = (int)$this->user['id'];
        $isTrial = ($this->user['plan_status'] ?? '') === 'trial';
        if ($isTrial && $this->auth->getTrialMessagesSent($uid) >= self::TRIAL_MESSAGE_LIMIT) {
            throw new ApiError('trial_expired', t('error.trial_expired'), 403);
        }
        $gemini = new GeminiClient($this->config['gemini_api_key'] ?? '', $this->config['gemini_api_key_backup'] ?? '');
        $convId = isset($this->input['conversation_id']) ? (int)$this->input['conversation_id'] : null;
        $topic = isset($this->input['topic_id']) ? (string)$this->input['topic_id'] : null;
        $result = (new Chat($this->db, $this->config))->handleMessage($uid, $message, $gemini, $convId, $topic);
        if (!empty($result['error'])) {
            $code = $result['code'] ?? 'chat_failed';
            $status = match (true) {
                $code === 'quota_exhausted', str_ends_with($code, '_paused') => 403,
                str_starts_with($code, 'rate_'), $code === 'trial_ip_daily' => 429,
                default => 200, // never a 5xx: Cloudflare would replace the body
            };
            throw new ApiError($code, $code === 'quota_exhausted' ? t('api.quota_exhausted') : $result['error'], $status);
        }
        $result['message_id'] = (int)($this->db->fetchOne(
            "SELECT id FROM messages WHERE conversation_id = ? AND role = 'ai' ORDER BY id DESC LIMIT 1",
            [(int)$result['conversationId']]
        )['id'] ?? 0);
        $result['is_trial'] = $isTrial;
        if ($isTrial) {
            $result['trial_remaining'] = max(0, self::TRIAL_MESSAGE_LIMIT - $this->auth->getTrialMessagesSent($uid));
        }
        $this->ok($result);
    }

    /** "Report this reply" on an AI message (Google Play AI-content policy). */
    private function reportMessage(string $id): void
    {
        $msg = $this->db->fetchOne(
            "SELECT m.id, m.content FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.id = ? AND c.user_id = ? AND m.role = 'ai'",
            [(int)$id, $this->user['id']]
        );
        if (!$msg) {
            throw new ApiError('not_found', t('api.not_found'), 404);
        }
        $reasons = ['offensive', 'harmful', 'wrong', 'other'];
        $reason = in_array($this->input['reason'] ?? '', $reasons, true) ? $this->input['reason'] : 'other';
        $this->db->execute(
            'INSERT INTO ai_reports (user_id, message_id, reason, note, message_snapshot) VALUES (?, ?, ?, ?, ?)',
            [$this->user['id'], $msg['id'], $reason, mb_substr(trim($this->str('note')), 0, 1000), mb_substr((string)$msg['content'], 0, 4000)]
        );
        $this->ok(['reported' => true]);
    }

    // ── Flashcards ────────────────────────────────────────────

    /**
     * The deck language: ?lang= / body "lang" when it is a learnable
     * language, else the user's target language. Lets people study cards in
     * any language without changing their profile.
     */
    private function deckLang(): string
    {
        $want = strtolower((string)($_GET['lang'] ?? $this->str('lang')));
        if ($want !== '' && Language::isUsable($want, 'learn')) {
            return $want;
        }
        return $this->user['target_lang'] ?? 'en';
    }

    /** Starter deck the first time a language is opened (see Flashcard::ensureStarterDeck). */
    private function ensureStarterDeck(Flashcard $fc, string $lang): void
    {
        $fc->ensureStarterDeck((int)$this->user['id'], $lang, $this->user['native_lang'] ?? 'en');
    }

    private function flashcardStats(): void
    {
        $fc = new Flashcard($this->db);
        $lang = $this->deckLang();
        $this->ensureStarterDeck($fc, $lang);
        $this->ok($fc->getStats((int)$this->user['id'], $lang) + ['lang' => $lang]);
    }

    private function flashcardCategories(): void
    {
        $this->ok(['categories' => (new Flashcard($this->db))->getCategories((int)$this->user['id'], $this->deckLang())]);
    }

    private function flashcardLanguages(): void
    {
        $learn = Language::listed('learn', $this->user['target_lang'] ?? null);
        // No deck in your own native language (same as the web picker).
        $learn = array_values(array_diff($learn, [strtolower($this->user['native_lang'] ?? '')]));
        $this->ok([
            'current' => $this->user['target_lang'] ?? 'en',
            'languages' => (new Flashcard($this->db))->getLanguages((int)$this->user['id'], $learn),
        ]);
    }

    /** ?tab=due|all|favorites|learned|mine|chat (&category=&q=&level=&offset=&lang=). */
    private function flashcards(): void
    {
        $fc = new Flashcard($this->db);
        $lang = $this->deckLang();
        $this->ensureStarterDeck($fc, $lang);
        $uid = (int)$this->user['id'];
        $tab = (string)($_GET['tab'] ?? 'due');
        if ($tab === 'due') {
            $cards = $fc->getDueCards($uid, $lang);
        } else {
            $view = in_array($tab, Flashcard::VIEWS, true) ? $tab : 'all';
            $level = isset($_GET['level']) && in_array($_GET['level'], Flashcard::PACK_LEVELS, true) ? $_GET['level'] : null;
            $cards = $fc->getAllCards($uid, $lang, (string)($_GET['category'] ?? 'all'), (string)($_GET['q'] ?? ''), 60, $level, $view, (int)($_GET['offset'] ?? 0));
        }
        $this->ok(['cards' => $cards, 'lang' => $lang]);
    }

    private function cardResult(array $r): void
    {
        if (!empty($r['error'])) {
            $status = $r['error'] === 'not_found' ? 404 : ($r['error'] === 'duplicate' ? 409 : 422);
            $extra = isset($r['existing_id']) ? ['existing_id' => $r['existing_id']] : [];
            throw new ApiError($r['error'], t('fc.err_' . $r['error']), $status, $extra);
        }
        $this->ok(['card' => $r['card']]);
    }

    private function flashcardCreate(): void
    {
        $fc = new Flashcard($this->db);
        $uid = (int)$this->user['id'];
        $r = $fc->createCard($uid, $this->deckLang(), $this->input);
        // Same as the web: a new card goes into the open list, else "Saved".
        if (!empty($r['card'])) {
            $cl = new CardLists($this->db);
            $into = (int)($this->input['list_id'] ?? 0);
            $into = $into && $cl->get($uid, $into) ? $into : $cl->defaultId($uid, $this->deckLang());
            $cl->add($uid, $into, [(int)$r['card']['id']]);
            $r['card'] = $fc->getCard($uid, (int)$r['card']['id']);
        }
        $this->cardResult($r);
    }

    // ── Word bank ───────────────────────────────────────────────────

    /** ?lang= — every word of the deck language, with card_id when the user has it. */
    private function words(): void
    {
        $lang = $this->deckLang();
        $this->ok(['words' => (new Flashcard($this->db))->wordBank((int)$this->user['id'], $lang, $this->user['native_lang'] ?? 'en'), 'lang' => $lang]);
    }

    /** {word, lang} — adds the word to the user's cards and their "Saved" list. */
    private function wordAdd(): void
    {
        $uid = (int)$this->user['id'];
        $lang = $this->deckLang();
        $r = (new Flashcard($this->db))->addFromBank($uid, $lang, $this->user['native_lang'] ?? 'en', $this->str('word'));
        if (empty($r['card'])) {
            $code = $r['error'] ?? 'generic';
            throw new ApiError($code, t('fc.err_' . $code), $code === 'word_not_in_bank' ? 404 : 422);
        }
        $cl = new CardLists($this->db);
        $cl->add($uid, $cl->defaultId($uid, $lang), [(int)$r['card']['id']]);
        $this->ok(['card' => (new Flashcard($this->db))->getCard($uid, (int)$r['card']['id'])]);
    }

    // ── Card lists (playlists), see CardLists ──────────────────────

    /** Error codes from CardLists → HTTP status (never 5xx). */
    private function listResult(array $r, string $key): void
    {
        if (!empty($r['error'])) {
            $status = ['list_not_found' => 404, 'not_found' => 404, 'list_default' => 409, 'list_limit' => 409, 'list_full' => 409][$r['error']] ?? 422;
            throw new ApiError($r['error'], t('fc.err_' . $r['error']), $status);
        }
        $this->ok($key === '' ? $r : [$key => $r[$key]] + array_intersect_key($r, ['added' => 1]));
    }

    /** ?lang= — the lists of one deck language, "Saved" first. */
    private function lists(): void
    {
        $lang = $this->deckLang();
        $this->ok(['lists' => (new CardLists($this->db))->all((int)$this->user['id'], $lang), 'lang' => $lang]);
    }

    /** {name, lang?, cards?: [ids]} */
    private function listCreate(): void
    {
        $cl = new CardLists($this->db);
        $uid = (int)$this->user['id'];
        $r = $cl->create($uid, $this->deckLang(), $this->str('name'));
        if (!empty($r['list']) && is_array($this->input['cards'] ?? null)) {
            $cl->add($uid, $r['list']['id'], $this->input['cards']);
            $r['list'] = $cl->get($uid, $r['list']['id']);
        }
        $this->listResult($r, 'list');
    }

    private function listRename(string $id): void
    {
        $this->listResult((new CardLists($this->db))->rename((int)$this->user['id'], (int)$id, $this->str('name')), 'list');
    }

    private function listDelete(string $id): void
    {
        $this->listResult((new CardLists($this->db))->delete((int)$this->user['id'], (int)$id), '');
    }

    /** ?offset= — the list's cards in the order they were added (up to 200 a page). */
    private function listCards(string $id): void
    {
        $uid = (int)$this->user['id'];
        $list = (new CardLists($this->db))->get($uid, (int)$id);
        if (!$list) {
            throw new ApiError('list_not_found', t('fc.err_list_not_found'), 404);
        }
        $cards = (new Flashcard($this->db))->getAllCards($uid, $list['language'], null, null, 200, null, 'all', max(0, (int)($_GET['offset'] ?? 0)), (int)$id);
        $this->ok(['list' => $list, 'cards' => $cards]);
    }

    /** {cards: [ids], from?: listId} — "from" makes it a move. */
    private function listAdd(string $id): void
    {
        $ids = is_array($this->input['cards'] ?? null) ? $this->input['cards'] : [];
        $this->listResult((new CardLists($this->db))->add((int)$this->user['id'], (int)$id, $ids, (int)($this->input['from'] ?? 0)), 'list');
    }

    /** {cards: [ids]} */
    private function listRemove(string $id): void
    {
        $ids = is_array($this->input['cards'] ?? null) ? $this->input['cards'] : [];
        $this->listResult((new CardLists($this->db))->remove((int)$this->user['id'], (int)$id, $ids), 'list');
    }

    private function flashcard(string $id): void
    {
        $card = (new Flashcard($this->db))->getCard((int)$this->user['id'], (int)$id);
        $this->cardResult($card ? ['card' => $card] : ['error' => 'not_found']);
    }

    private function flashcardUpdate(string $id): void
    {
        $this->cardResult((new Flashcard($this->db))->updateCard((int)$this->user['id'], (int)$id, $this->input));
    }

    private function flashcardDelete(string $id): void
    {
        if (!(new Flashcard($this->db))->deleteCard((int)$this->user['id'], (int)$id)) {
            throw new ApiError('not_found', t('fc.err_not_found'), 404);
        }
        $this->ok(['deleted' => true]);
    }

    private function flashcardFavorite(string $id): void
    {
        $card = (new Flashcard($this->db))->setFavorite((int)$this->user['id'], (int)$id, !empty($this->input['on']));
        $this->cardResult($card ? ['card' => $card] : ['error' => 'not_found']);
    }

    private function flashcardLearned(string $id): void
    {
        $card = (new Flashcard($this->db))->setLearned((int)$this->user['id'], (int)$id, !empty($this->input['on']));
        $this->cardResult($card ? ['card' => $card] : ['error' => 'not_found']);
    }

    private function flashcardReview(): void
    {
        $quality = (int)($this->input['quality'] ?? -1);
        if (!isset($this->input['vocab_id']) || $quality < 0 || $quality > 5) {
            throw new ApiError('validation', t('api.invalid_value'), 422);
        }
        $r = (new Flashcard($this->db))->reviewCard((int)$this->user['id'], (int)$this->input['vocab_id'], $quality);
        if (empty($r['success'])) {
            throw new ApiError('not_found', t('fc.err_not_found'), 404);
        }
        $this->ok($r);
    }

    private function flashcardPacks(): void
    {
        $this->ok(['packs' => (new Flashcard($this->db))->getPacks((int)$this->user['id'], $this->deckLang())]);
    }

    private function flashcardImportPack(): void
    {
        $r = (new Flashcard($this->db))->importPack(
            (int)$this->user['id'], $this->deckLang(), $this->user['native_lang'] ?? 'en', $this->str('level')
        );
        if (!empty($r['error'])) {
            throw new ApiError('validation', (string)$r['error'], 422);
        }
        $this->ok($r);
    }

    // ── Mistakes ──────────────────────────────────────────────

    private function mistakes(): void
    {
        $svc = new Mistakes($this->db);
        $lang = $this->user['target_lang'] ?? 'en';
        $svc->sync((int)$this->user['id'], $lang);
        $this->ok(['mistakes' => $svc->getAll((int)$this->user['id'], $lang)]);
    }

    private function mistakeReview(string $id): void
    {
        $r = (new Mistakes($this->db))->review((int)$this->user['id'], (int)$id, $this->str('action'));
        if (!$r) {
            throw new ApiError('not_found', t('api.not_found'), 404);
        }
        $this->ok(['mistake' => $r]);
    }

    // ── Alphabet ──────────────────────────────────────────────

    /** ?lang= (default: the user's target language). Public so the app can show it before sign-in too. */
    private function alphabet(): void
    {
        $alphabets = require __DIR__ . '/../../data/alphabets.php';
        $lang = (string)($_GET['lang'] ?? ($this->user['target_lang'] ?? 'en'));
        if (!isset($alphabets[$lang]) || !Language::isUsable($lang, 'learn')) {
            throw new ApiError('not_found', t('api.not_found'), 404);
        }
        $learned = $this->user
            ? array_column($this->db->fetchAll('SELECT letter_key FROM alphabet_progress WHERE user_id = ? AND lang = ?', [$this->user['id'], $lang]), 'letter_key')
            : [];
        $this->ok([
            'lang' => $lang,
            'available' => array_values(array_filter(Language::listed('learn', $lang), fn($c) => isset($alphabets[$c]))),
            'alphabet' => $alphabets[$lang],
            'learned' => $learned,
        ]);
    }

    private function alphabetToggle(): void
    {
        $lang = $this->str('lang');
        $key = trim($this->str('letter_key'));
        if ($lang === '' || $key === '') {
            throw new ApiError('validation', t('api.invalid_value'), 422);
        }
        $uid = $this->user['id'];
        $existing = $this->db->fetchOne('SELECT id FROM alphabet_progress WHERE user_id = ? AND lang = ? AND letter_key = ?', [$uid, $lang, $key]);
        if ($existing) {
            $this->db->execute('DELETE FROM alphabet_progress WHERE id = ?', [$existing['id']]);
        } else {
            $this->db->execute('INSERT INTO alphabet_progress (user_id, lang, letter_key) VALUES (?, ?, ?)', [$uid, $lang, $key]);
        }
        $this->ok(['learned' => !$existing]);
    }

    // ── Account ───────────────────────────────────────────────

    private function accountExport(): void
    {
        $this->ok(Account::export($this->db, (int)$this->user['id']));
    }

    /**
     * Deletes the account (Google Play requires in-app deletion). Confirmed
     * with the password, or — for accounts that sign in with Google and so
     * have no password of their own — by typing the account's email.
     */
    private function accountDelete(): void
    {
        $u = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$this->user['id']]);
        $password = $this->str('password');
        $confirmEmail = trim($this->str('confirm_email'));
        $ok = ($password !== '' && password_verify($password, $u['password'] ?? ''))
            || (!empty($u['google_id']) && $confirmEmail !== '' && strcasecmp($confirmEmail, (string)$u['email']) === 0);
        if (!$ok) {
            throw new ApiError('confirmation_failed', t('account.wrong_password'), 403);
        }
        Account::delete(
            $this->db, $u,
            new \App\Src\DodoClient($this->config['dodo_api_key'] ?? '', $this->config['dodo_environment'] ?? 'live'),
            new \App\Src\FastSpringClient($this->config['fastspring_api_username'] ?? '', $this->config['fastspring_api_password'] ?? ''),
            new \App\Src\PaddleClient($this->config['paddle_api_key'] ?? '', $this->config['paddle_environment'] ?? 'sandbox'),
            'app'
        );
        $this->ok(['deleted' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────

    /** Same rule as the website's Auth::hasPaid(). */
    private function hasPlan(): bool
    {
        return $this->user !== null && $this->hasPlanRow($this->user);
    }

    private function freshUser(int $id): array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]) ?? [];
    }

    /** The account as the app sees it. Add fields freely; never rename or remove one. */
    private function userPayload(array $u): array
    {
        $uid = (int)$u['id'];
        $plan = (string)($u['plan_status'] ?? 'inactive');
        $tm = new TokenManager($this->db);
        $bonus = (int)($this->db->fetchOne('SELECT bonus_limit FROM token_usage WHERE user_id = ?', [$uid])['bonus_limit'] ?? 0);
        $xp = (int)($u['xp'] ?? 0);
        $provider = !empty($u['dodo_subscription_id']) ? 'dodo'
            : (!empty($u['fastspring_subscription_id']) ? 'fastspring'
            : (!empty($u['paddle_subscription_id']) ? 'paddle' : ((int)($u['has_paid'] ?? 0) === 1 ? 'manual' : null)));
        $payload = [
            'id' => $uid,
            'email' => $u['email'],
            'name' => $u['name'] ?? '',
            'avatar_url' => $u['profile_image'] ?? null,
            'native_lang' => $u['native_lang'] ?? 'en',
            'target_lang' => $u['target_lang'] ?? 'en',
            'ui_lang' => $u['ui_lang'] ?? null,
            'cefr_level' => $u['cefr_level'] ?? 'A1',
            'learning_goal' => $u['learning_goal'] ?? 'conversation',
            'interest_area' => $u['interest_area'] ?? 'general',
            'onboarding_completed' => (int)($u['onboarding_completed'] ?? 0) === 1,
            'email_verified' => !empty($u['email_verified_at']),
            'google_linked' => !empty($u['google_id']),
            'xp' => $xp,
            'level' => max(1, intdiv($xp, 100) + 1),
            'xp_in_level' => $xp % 100,
            'streak' => (int)($u['streak_count'] ?? 0),
            'created_at' => $u['created_at'] ?? null,
            'plan' => [
                // 'active' is the internal name of the Premium plan.
                'status' => $plan,
                'has_access' => $this->hasPlanRow($u),
                'provider' => $provider,
                'interval' => $u['billing_interval'] ?? 'month',
                'next_billed_at' => $u['next_billed_at'] ?? null,
                'cancel_requested' => !empty($u['cancel_requested_at']),
                'pending_change' => $u['pending_plan_change'] ?? null,
            ],
            'quota' => [
                'remaining' => $tm->getRemaining($uid),
                'total' => $tm->getBaseLimit($plan) + $bonus,
            ],
        ];
        if ($plan === 'trial') {
            $sent = $this->auth->getTrialMessagesSent($uid);
            $payload['trial'] = ['limit' => self::TRIAL_MESSAGE_LIMIT, 'sent' => $sent, 'remaining' => max(0, self::TRIAL_MESSAGE_LIMIT - $sent)];
        }
        return $payload;
    }

    private function hasPlanRow(array $u): bool
    {
        return in_array($u['plan_status'] ?? '', ['active', 'trial'], true) || (int)($u['has_paid'] ?? 0) === 1;
    }

    private function loadLanguage(): void
    {
        $candidates = [
            (string)($_SERVER['HTTP_X_APP_LANG'] ?? ''),
            (string)($this->user['ui_lang'] ?? ''),
            (string)($this->user['native_lang'] ?? ''),
            substr((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 2),
        ];
        foreach ($candidates as $c) {
            $c = strtolower(trim($c));
            if ($c !== '' && Language::isUsable($c, 'ui')) {
                Language::load($c);
                return;
            }
        }
        Language::load(Language::DEFAULT);
    }

    private function readInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new ApiError('invalid_json', 'Request body must be JSON', 400);
            }
            return $data;
        }
        return $_POST;
    }

    private function readBearer(): string
    {
        $h = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : '';
    }

    private function str(string $key): string
    {
        $v = $this->input[$key] ?? '';
        return is_scalar($v) ? (string)$v : '';
    }

    private function ok($data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function fail(string $code, string $message, int $status, array $extra = []): void
    {
        http_response_code($status >= 500 ? 200 : $status);
        echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message] + $extra], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
