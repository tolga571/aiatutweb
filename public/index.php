<?php
error_reporting(0);

require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Auth;
use App\Src\Chat;
use App\Src\GeminiClient;
use App\Src\AdminController;
use App\Src\Language;
use App\Src\Flashcard;

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// Initialize database first (needed for session handler)
$db = new Database($config['db_url']);

// Mobile app API (/api/v1/...): token-based, so it's handled before any
// session is started and never sets a cookie. See src/Api/Router.php.
$apiPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($apiPath, '/api/v1/') || $apiPath === '/api/v1') {
    Language::boot($db);
    (new \App\Src\Api\Router($db, $config))->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', substr($apiPath, strlen('/api/v1')));
    exit;
}

// Use database-backed sessions so they survive Railway deploys
// Session stays alive for 90 days of inactivity, refreshed on each visit
$sessionLifetime = 86400 * 90; // 90 days
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$sessionHandler = new \App\Src\DatabaseSessionHandler($db->getPdo());
session_set_save_handler($sessionHandler, true);
session_start();

$auth   = new Auth($db);
$chat   = new Chat($db, $config);
$gemini = new GeminiClient($config['gemini_api_key'], $config['gemini_api_key_backup']);
$adminCtrl = new AdminController($db);
$flashcard = new Flashcard($db);
$paddleClient = new \App\Src\PaddleClient($config['paddle_api_key'] ?? '', $config['paddle_environment'] ?? 'sandbox');
$fastspringClient = new \App\Src\FastSpringClient($config['fastspring_api_username'] ?? '', $config['fastspring_api_password'] ?? '');
$fastspringBilling = new \App\Src\FastSpringBilling($db, $config, $fastspringClient);
$dodoClient = new \App\Src\DodoClient($config['dodo_api_key'] ?? '', $config['dodo_environment'] ?? 'live');
$dodoBilling = new \App\Src\DodoBilling($db, $config, $dodoClient);

// Initialize language system. Interface language, most specific first:
// the user's saved pick (users.ui_lang) > this browser's pick (cookie) >
// a signed-in user's native language > English.
Language::boot($db);
$detectedLang = Language::DEFAULT;
$uiCookie = (string)($_COOKIE[Language::COOKIE] ?? '');
if ($uiCookie !== '' && Language::isUsable($uiCookie, 'ui')) {
    $detectedLang = $uiCookie;
} else {
    // Nothing picked yet: follow the browser's language list (most visitors
    // come from abroad), else English.
    $uiCookie = '';
    $detectedLang = Language::fromAcceptLanguage((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) ?? Language::DEFAULT;
}
// ?ui_lang=xx from the language picker: remember it (cookie, and on the
// account when signed in), then reload the same URL without the parameter.
if (isset($_GET['ui_lang']) && is_string($_GET['ui_lang'])) {
    $pick = strtolower($_GET['ui_lang']);
    if (Language::isUsable($pick, 'ui')) {
        setcookie(Language::COOKIE, $pick, [
            'expires' => time() + 86400 * 365, 'path' => '/',
            'secure' => $isHttps, 'httponly' => false, 'samesite' => 'Lax',
        ]);
        if ($auth->isLoggedIn()) {
            $db->execute('UPDATE users SET ui_lang = ? WHERE id = ?', [$pick, $auth->userId()]);
        }
    }
    $q = $_GET;
    unset($q['ui_lang']);
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    header('Location: ' . $path . ($q ? '?' . http_build_query($q) : ''));
    exit;
}
if ($auth->isLoggedIn()) {
    $currentUser = $auth->currentUser();
    // An admin can suspend an account (or delete it) while its session is
    // still open — end that session on the next request.
    if (!$currentUser || !empty($currentUser['suspended_at'])) {
        $wasSuspended = !empty($currentUser['suspended_at']);
        $auth->logout();
        header('Location: ?page=login' . ($wasSuspended ? '&suspended=1' : ''));
        exit;
    }
    if (!empty($currentUser['ui_lang']) && Language::isUsable($currentUser['ui_lang'], 'ui')) {
        $detectedLang = $currentUser['ui_lang'];
    } elseif ($uiCookie === '' && !empty($currentUser['onboarding_completed'])
        && Language::isUsable($currentUser['native_lang'] ?? '', 'ui')) {
        $detectedLang = $currentUser['native_lang'];
    }
}
Language::load($detectedLang);

// FrankenPHP/Caddy's `rewrite` directive doesn't reliably hand this app's
// query-string router a rewritten path (see php/frankenphp#1895), so clean
// URLs like /pricing are resolved here instead of in the Caddyfile. An
// unrecognized path (not '/', not an explicit ?page=) is passed through
// as-is so it falls into the switch's own default case (404) instead of
// being folded into 'home' — which used to make an unknown path
// indistinguishable from the real homepage. The switch's case list below
// is the single source of truth for which paths are valid.
$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
if (isset($_GET['page'])) {
    $page = $_GET['page'];
} elseif ($requestPath === '') {
    $page = 'home';
} else {
    $page = $requestPath;
}

// Auth-required pages helper
$requireAuth = function() use ($auth, $page) {
    if (!$auth->isLoggedIn()) {
        header('Location: ?page=login&redirect=' . urlencode($page));
        exit;
    }
};
$requirePlan = function() use ($auth) {
    if (!$auth->isLoggedIn()) { header('Location: ?page=login'); exit; }
    // Also catches people already signed in who were sent back to pick
    // their native language (see Database languages_rev_3). Onboarding then
    // goes on to start-trial, which passes anyone with a plan to the chat.
    if (!$auth->hasCompletedOnboarding()) { header('Location: ?page=onboarding'); exit; }
    if (!$auth->hasPaid())    { header('Location: ?page=pricing'); exit; }
};
// Flashcard deck language: the one picked on the flashcards page this
// session, else the user's target language.
$fcDeckLang = function() use ($auth): string {
    $picked = (string)($_SESSION['fc_lang'] ?? '');
    if ($picked !== '' && \App\Src\Language::isUsable($picked, 'learn')) {
        return $picked;
    }
    return $auth->currentUser()['target_lang'] ?? 'en';
};

switch ($page) {

    // ── Auth ─────────────────────────────────────────────────────
    case 'login':
        if (isset($_SESSION['login_error'])) {
            $loginError = $_SESSION['login_error'];
            unset($_SESSION['login_error']);
        }
        if (!empty($_GET['suspended'])) {
            $loginError = __('auth.account_suspended');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientIp = client_ip();
            if ($auth->tooManyAttempts($clientIp, 'login', 8, 900)) {
                $loginError = __('auth.error_too_many_attempts');
            } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $loginError = __('auth.error_generic');
            } else {
                $email = trim($_POST['email'] ?? '');
                $pass  = $_POST['password'] ?? '';
                if ($auth->login($email, $pass)) {
                    $auth->clearAttempts($clientIp, 'login');
                    $redirect = $_GET['redirect'] ?? 'dashboard';
                    header('Location: ?page=' . urlencode($redirect)); exit;
                }
                $auth->recordAttempt($clientIp, 'login');
                $loginError = $auth->lastError ?: __('auth.error_generic');
            }
        }
        require __DIR__ . '/../views/login.php';
        break;

    case 'register':
        if (isset($_SESSION['register_error'])) {
            $registerError = $_SESSION['register_error'];
            unset($_SESSION['register_error']);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientIp = client_ip();
            $errors = [];
            $abuse = new \App\Src\AbuseGuard($db, $config);
            if ($auth->tooManyAttempts($clientIp, 'register', 5, 3600) || $abuse->signupBlocked($clientIp)) {
                $errors[] = __('auth.error_too_many_attempts');
            } else {
                $auth->recordAttempt($clientIp, 'register');
                $email = trim($_POST['email'] ?? '');
                $pass  = $_POST['password'] ?? '';
                $pass_confirm = $_POST['password_confirm'] ?? '';
                $name  = trim($_POST['name'] ?? '');
                $terms = isset($_POST['terms']);
                if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                    $errors[] = __('auth.error_generic');
                }
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = __('auth.error_invalid_email');
                }
                if (empty($name) || mb_strlen($name) < 2) {
                    $errors[] = __('auth.error_invalid_name');
                }
                if (strlen($pass) < 8) {
                    $errors[] = __('auth.error_weak_password');
                }
                if ($pass !== $pass_confirm) {
                    $errors[] = __('auth.error_password_mismatch');
                }
                if (!$terms) {
                    $errors[] = __('auth.error_terms');
                }
                if (empty($errors)) {
                    if ($auth->register($email, $pass, $name)) {
                        $auth->clearAttempts($clientIp, 'register');
                        $abuse->recordSignup($clientIp);
                        $auth->login($email, $pass);
                        if ($auth->userId()) {
                            $verifyToken = $auth->createEmailVerificationToken($auth->userId());
                            $verifyUrl = 'https://jumplearner.com/?page=verify-email&token=' . urlencode($verifyToken);
                            (new \App\Src\Mailer($config))->send(
                                $email,
                                __('auth.verify_email_subject'),
                                '<p>' . __('auth.verify_email_body') . '</p><p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>'
                            );
                        }
                        $auth->logout(); // Force them to verify email before logging in
                        header('Location: ?page=awaiting-verification'); exit;
                    }
                    $errors[] = $auth->lastError ?: __('auth.registration_failed');
                }
            }
            $registerError = $errors;
        }
        require __DIR__ . '/../views/register.php';
        break;

    case 'forgot-password':
        $forgotSent = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clientIp = client_ip();
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $forgotError = __('auth.error_generic');
            } elseif ($auth->tooManyAttempts($clientIp, 'password-reset', 5, 3600)) {
                $forgotError = __('auth.error_too_many_attempts');
            } else {
                $auth->recordAttempt($clientIp, 'password-reset');
                $email = trim($_POST['email'] ?? '');
                $resetUser = filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? $db->fetchOne('SELECT id FROM users WHERE email = ?', [$email])
                    : null;
                // Always report success either way — confirming whether an
                // email is registered is its own information leak.
                if ($resetUser) {
                    $resetToken = $auth->createPasswordResetToken((int)$resetUser['id']);
                    $resetUrl = 'https://jumplearner.com/?page=reset-password&token=' . urlencode($resetToken);
                    (new \App\Src\Mailer($config))->send(
                        $email,
                        __('auth.reset_email_subject'),
                        '<p>' . __('auth.reset_email_body') . '</p><p><a href="' . htmlspecialchars($resetUrl) . '">' . htmlspecialchars($resetUrl) . '</a></p>'
                    );
                }
                $forgotSent = true;
            }
        }
        require __DIR__ . '/../views/forgot-password.php';
        break;

    case 'reset-password':
        $resetToken = $_GET['token'] ?? ($_POST['token'] ?? '');
        $resetUserId = $resetToken !== '' ? $auth->findValidResetUserId($resetToken) : null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetUserId) {
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $resetError = __('auth.error_generic');
            } else {
                $newPass = $_POST['password'] ?? '';
                $newPassConfirm = $_POST['password_confirm'] ?? '';
                if (strlen($newPass) < 8) {
                    $resetError = __('auth.error_weak_password');
                } elseif ($newPass !== $newPassConfirm) {
                    $resetError = __('auth.error_password_mismatch');
                } elseif ($auth->completePasswordReset($resetToken, $newPass)) {
                    $auth->clearAttempts(client_ip(), 'password-reset');
                    $_SESSION['user_id'] = $resetUserId;
                    session_regenerate_id(true);
                    header('Location: ?page=dashboard&password_reset=1');
                    exit;
                } else {
                    $resetError = __('auth.reset_invalid_token');
                }
            }
        }
        require __DIR__ . '/../views/reset-password.php';
        break;

    case 'awaiting-verification':
        require __DIR__ . '/../views/pages/awaiting-verification.php';
        break;

    case 'verify-email':
        $verifyOk = $auth->verifyEmailToken($_GET['token'] ?? '');
        if ($auth->isLoggedIn()) {
            header('Location: ?page=dashboard&email_verified=' . ($verifyOk ? '1' : '0'));
            exit;
        }
        header('Location: ?page=login&email_verified=' . ($verifyOk ? '1' : '0'));
        exit;

    case 'google-login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['credential'])) {
            $credential = $_POST['credential'];
            $googleClientId = $config['google_client_id'] ?? '';
            
            if (empty($googleClientId)) {
                $_SESSION['login_error'] = __('auth.google_config_warning');
                header('Location: ?page=login');
                exit;
            }
            
            $data = Auth::verifyGoogleIdToken($credential, [$googleClientId]);
            if ($data) {
                $abuse = new \App\Src\AbuseGuard($db, $config);
                [$user, $created] = $auth->findOrCreateGoogleUser($data, !$abuse->signupBlocked(client_ip()));
                if ($created) {
                    $abuse->recordSignup(client_ip());
                }
                if (!$user) {
                    $_SESSION['login_error'] = __('auth.error_too_many_attempts');
                    header('Location: ?page=login');
                    exit;
                }
                if ($user) {
                    if (!empty($user['suspended_at'])) {
                        $_SESSION['login_error'] = __('auth.account_suspended');
                        header('Location: ?page=login');
                        exit;
                    }

                    // Log user in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $auth->recordActivity($user);

                    if ($auth->hasCompletedOnboarding()) {
                        $redirect = $_GET['redirect'] ?? 'dashboard';
                        header('Location: ?page=' . urlencode($redirect));
                    } else {
                        $redirect = isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '';
                        header('Location: ?page=onboarding' . $redirect);
                    }
                    exit;
                }
            }
            
            $_SESSION['login_error'] = __('auth.invalid_credentials');
            header('Location: ?page=login');
            exit;
        }
        header('Location: ?page=login');
        exit;

    case 'logout':
        $auth->logout();
        header('Location: ?page=login'); exit;

    // ── Onboarding ────────────────────────────────────────────────
    case 'onboarding':
        $requireAuth();
        if ($auth->hasCompletedOnboarding()) {
            $redirect = $_GET['redirect'] ?? 'dashboard';
            header('Location: ?page=' . urlencode($redirect)); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $native = Auth::pick($_POST['native_lang'] ?? null, Language::supportedLangs(), 'en');
            $target = Auth::pick($_POST['target_lang'] ?? null, Language::supportedLangs(), 'en');
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $onboardingError = __('auth.error_generic');
            } elseif ($native === $target) {
                $onboardingError = __('onboarding.same_lang_error');
            } else {
                $auth->saveOnboarding(
                    $auth->userId(),
                    $native,
                    $target,
                    $_POST['cefr_level']    ?? 'A1',
                    $_POST['learning_goal'] ?? 'conversation',
                    $_POST['interest_area'] ?? 'general'
                );
                if (Language::isUsable($native, 'ui')) {
                    setcookie(Language::COOKIE, $native, ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => $isHttps, 'httponly' => false, 'samesite' => 'Lax']);
                }
                $redirect = $_GET['redirect'] ?? 'start-trial';
                header('Location: ?page=' . urlencode($redirect)); exit;
            }
        }
        require __DIR__ . '/../views/onboarding.php';
        break;

    // ── Update Lang ────────────────────────────────────────────────
    case 'update_lang':
        $requireAuth();
        $lang = $_GET['lang'] ?? 'en';
        if (is_string($lang) && Language::isUsable($lang, 'learn')) {
            $currentUser = $auth->currentUser();
            if ($lang === ($currentUser['native_lang'] ?? '')) {
                $_SESSION['lang_notice'] = __('onboarding.same_lang_error');
                header('Location: ?page=chat'); exit;
            }
            $db->execute('UPDATE users SET target_lang = ? WHERE id = ?', [$lang, $auth->userId()]);
        }
        header('Location: ?page=chat'); exit;

    // ── Pricing ───────────────────────────────────
    case 'pricing':
        $currentUser = $auth->isLoggedIn() ? $auth->currentUser() : null;
        require __DIR__ . '/../views/pricing.php';
        break;

    case 'confirm-payment':
        $requireAuth();
        // The price ID the user actually checked out for, so that if the
        // Paddle webhook is slow, the timeout fallback below can grant the
        // plan that was really purchased instead of guessing.
        $priceId = $_GET['price_id'] ?? '';
        $purchasePlanMap = [
            $config['paddle_starter_price_id'] ?? ''        => 'starter',
            $config['paddle_starter_yearly_price_id'] ?? '' => 'starter',
            $config['paddle_pro_price_id'] ?? ''             => 'pro',
            $config['paddle_pro_yearly_price_id'] ?? ''      => 'pro',
            $config['paddle_premium_price_id'] ?? ''         => 'active',
            $config['paddle_premium_yearly_price_id'] ?? ''  => 'active',
        ];
        unset($purchasePlanMap['']);
        $purchasePlan = $purchasePlanMap[$priceId] ?? null;
        $yearlyPriceIds = array_filter([
            $config['paddle_starter_yearly_price_id'] ?? '',
            $config['paddle_pro_yearly_price_id'] ?? '',
            $config['paddle_premium_yearly_price_id'] ?? '',
        ]);
        $purchaseInterval = in_array($priceId, $yearlyPriceIds, true) ? 'year' : 'month';
        try {
            $db->execute(
                'UPDATE users SET payment_pending_at = ' . $db->now() . ', pending_purchase_plan = ?, pending_purchase_interval = ? WHERE id = ?',
                [$purchasePlan, $purchaseInterval, $auth->userId()]
            );
        } catch (\Throwable $e) {
            // Best-effort tracking for the timeout fallback below — if it
            // fails, the Paddle webhook still grants access on its own.
            error_log('confirm-payment: failed to record pending purchase: ' . $e->getMessage());
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    case 'fastspring-confirm-order':
        // Called by the pricing page when the FastSpring popup closes after
        // a purchase. Access is only granted after the order and its
        // subscription are confirmed through the FastSpring API; if the API
        // isn't configured, the webhook grants access instead.
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $orderId = trim((string)($_POST['order_id'] ?? ''));
        if ($orderId === '' || !preg_match('/^[A-Za-z0-9_\-]{6,64}$/', $orderId)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_order']);
            exit;
        }
        try {
            $result = $fastspringBilling->verifyOrderForUser((int)$auth->userId(), $orderId);
        } catch (\Throwable $e) {
            error_log('fastspring-confirm-order: ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'server_error'];
        }
        if (!$result['ok']) {
            error_log('fastspring-confirm-order: user ' . (int)$auth->userId() . ' order ' . $orderId . ' not verified: ' . $result['error']);
        }
        echo json_encode($result);
        exit;

    case 'dodo-checkout':
        // Dodo has no client-side checkout widget like Paddle/FastSpring —
        // the pricing page just links here, this creates a hosted checkout
        // session server-side, and the browser is redirected to it. Records
        // the same payment_pending_at / pending_purchase_plan fields Paddle
        // uses so the existing check-payment-status timeout fallback (see
        // below) covers Dodo too without any Dodo-specific polling logic.
        $requireAuth();
        $planKey = $_GET['plan'] ?? '';
        $interval = $_GET['interval'] ?? 'month';
        if (!in_array($planKey, ['starter', 'pro', 'active'], true) || !in_array($interval, ['month', 'year'], true)) {
            header('Location: ?page=pricing&dodo_error=invalid_plan');
            exit;
        }
        $productId = $dodoBilling->productIdFor($planKey, $interval);
        if ($productId === '' || !$dodoClient->isConfigured()) {
            header('Location: ?page=pricing&dodo_error=not_configured');
            exit;
        }
        $checkoutUser = $auth->currentUser();
        $checkoutUrl = $dodoClient->createCheckoutSession(
            $productId,
            $checkoutUser['email'] ?? '',
            $auth->userId(),
            'https://jumplearner.com/?page=pricing&dodo_return=1',
            'https://jumplearner.com/?page=pricing'
        );
        if (!$checkoutUrl) {
            header('Location: ?page=pricing&dodo_error=checkout_failed');
            exit;
        }
        try {
            $db->execute(
                'UPDATE users SET payment_pending_at = ' . $db->now() . ', pending_purchase_plan = ?, pending_purchase_interval = ? WHERE id = ?',
                [$planKey, $interval, $auth->userId()]
            );
        } catch (\Throwable $e) {
            // Best-effort — if it fails, the webhook still grants access on its own.
            error_log('dodo-checkout: failed to record pending purchase: ' . $e->getMessage());
        }
        header('Location: ' . $checkoutUrl);
        exit;

    case 'check-payment-status':
        // Security: plan access is ONLY granted by a verified provider
        // webhook (or a server-side order verification such as
        // fastspring-confirm-order). The previous fallback here trusted
        // client-controllable flags (payment_pending_at /
        // pending_purchase_plan) to grant paid plans without any payment —
        // a free-subscription bypass. Polling this endpoint now only
        // reflects webhook-confirmed state.
        $requireAuth();
        header('Content-Type: application/json');
        echo json_encode(['paid' => $auth->hasPaid()]);
        exit;

    case 'start-trial':
        $requireAuth();
        // One free trial per few accounts per network, none for throw-away
        // mailboxes (AbuseGuard); refused users are sent to the plans.
        $trialRefusal = (new \App\Src\AbuseGuard($db, $config))->startTrial($auth->currentUser(), client_ip());
        if ($trialRefusal) {
            $_SESSION['pricing_notice'] = __('error.' . $trialRefusal);
            header('Location: ?page=pricing');
            exit;
        }
        header('Location: ?page=chat');
        exit;

    // ── Subscription management ─────────────────────────────────────
    case 'cancel-subscription':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $cancelUser = $auth->currentUser();
        if (!$auth->hasPaid() || ($cancelUser['plan_status'] ?? '') === 'trial') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'no_active_subscription']);
            exit;
        }
        if (!empty($cancelUser['pending_plan_change'])) {
            // A downgrade is already scheduled for next billing period —
            // cancelling on top of that would leave Paddle and our own
            // pending_plan_change flag out of sync. Resume first.
            echo json_encode(['ok' => false, 'error' => 'change_pending']);
            exit;
        }
        $dodoSubId = $cancelUser['dodo_subscription_id'] ?? null;
        if ($dodoSubId && $dodoClient->isConfigured()) {
            if ($dodoClient->cancelSubscription($dodoSubId)) {
                $db->execute("UPDATE users SET cancel_requested_at = " . $db->now() . ", cancel_method = 'api' WHERE id = ?", [$auth->userId()]);
                \App\Src\ActivityLog::record($db, $auth->userId(), 'cancellation_requested', 'dodo', $cancelUser['plan_status'] ?? null, null, "user {$auth->userId()} scheduled cancellation (api)");
                echo json_encode(['ok' => true, 'method' => 'api']);
            } else {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
            }
            exit;
        }
        $fsSubId = $cancelUser['fastspring_subscription_id'] ?? null;
        if ($fsSubId && $fastspringClient->isConfigured()) {
            if ($fastspringClient->cancelSubscription($fsSubId)) {
                $db->execute("UPDATE users SET cancel_requested_at = " . $db->now() . ", cancel_method = 'api' WHERE id = ?", [$auth->userId()]);
                \App\Src\ActivityLog::record($db, $auth->userId(), 'cancellation_requested', 'fastspring', $cancelUser['plan_status'] ?? null, null, "user {$auth->userId()} scheduled cancellation (api)");
                echo json_encode(['ok' => true, 'method' => 'api']);
            } else {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
            }
            exit;
        }
        $subId = $cancelUser['paddle_subscription_id'] ?? null;
        if (!$dodoSubId && !$fsSubId && $subId && $paddleClient->isConfigured()) {
            $success = $paddleClient->cancelSubscription($subId, 'next_billing_period');
            if ($success) {
                $db->execute("UPDATE users SET cancel_requested_at = " . $db->now() . ", cancel_method = 'api' WHERE id = ?", [$auth->userId()]);
                \App\Src\ActivityLog::record($db, $auth->userId(), 'cancellation_requested', 'paddle', $cancelUser['plan_status'] ?? null, null, "user {$auth->userId()} scheduled cancellation (api)");
                echo json_encode(['ok' => true, 'method' => 'api']);
            } else {
                echo json_encode(['ok' => false, 'error' => 'paddle_api_failed']);
            }
            exit;
        }
        // No subscription ID on file yet (subscription predates this feature)
        // or no API key configured: record the request so support can finish
        // it manually instead of silently doing nothing.
        $db->execute("UPDATE users SET cancel_requested_at = " . $db->now() . ", cancel_method = 'manual' WHERE id = ?", [$auth->userId()]);
        \App\Src\ActivityLog::record($db, $auth->userId(), 'cancellation_requested', $dodoSubId ? 'dodo' : ($fsSubId ? 'fastspring' : 'paddle'), $cancelUser['plan_status'] ?? null, null, "user {$auth->userId()} requested cancellation (manual, no provider credentials on file)");
        if (!empty($config['mailtrap_api_token'])) {
            $mailer = new \App\Src\Mailer($config);
            $mailer->send(
                $config['mail_from_address'],
                'Manual cancellation request',
                '<p>User #' . (int)$auth->userId() . ' (' . htmlspecialchars($cancelUser['email'] ?? '') . ') requested to cancel their subscription, but no subscription ID / API credentials are on file for automatic cancellation. Please cancel manually in the ' . ($dodoSubId ? 'Dodo' : ($fsSubId ? 'FastSpring' : 'Paddle')) . ' dashboard.</p>'
            );
        }
        echo json_encode(['ok' => true, 'method' => 'manual']);
        exit;

    case 'resume-subscription':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $resumeUser = $auth->currentUser();
        $hasPendingCancel = !empty($resumeUser['cancel_requested_at']);
        $hasPendingChange = !empty($resumeUser['pending_plan_change']);
        if (!$hasPendingCancel && !$hasPendingChange) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'nothing_to_resume']);
            exit;
        }
        // A pending downgrade and a manually-recorded cancellation request
        // both undo the same way: a downgrade is always a real Paddle
        // scheduled_change, and a cancellation only needs a real API call
        // when cancel_method is 'api'.
        $needsApiCall = $hasPendingChange || ($resumeUser['cancel_method'] ?? '') === 'api';
        $dodoSubId = $resumeUser['dodo_subscription_id'] ?? null;
        if ($needsApiCall && $dodoSubId) {
            if (!$dodoClient->isConfigured()) {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
                exit;
            }
            $dodoOk = true;
            if ($hasPendingChange) {
                $currentProductId = $dodoBilling->productIdFor(
                    $resumeUser['plan_status'] ?? '',
                    ($resumeUser['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month'
                );
                $dodoOk = $currentProductId !== '' && $dodoClient->changeSubscriptionPlan($dodoSubId, $currentProductId, false);
            }
            if ($dodoOk && $hasPendingCancel && ($resumeUser['cancel_method'] ?? '') === 'api') {
                $dodoOk = $dodoClient->resumeSubscription($dodoSubId);
            }
            if (!$dodoOk) {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
                exit;
            }
        }
        $fsSubId = $resumeUser['fastspring_subscription_id'] ?? null;
        if ($needsApiCall && $fsSubId && !$dodoSubId) {
            if (!$fastspringClient->isConfigured()) {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
                exit;
            }
            $fsOk = true;
            if ($hasPendingChange) {
                // Undo a scheduled downgrade by switching back to the
                // product the user is still on, without proration.
                $currentPath = $fastspringBilling->pathFor(
                    $resumeUser['plan_status'] ?? '',
                    ($resumeUser['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month'
                );
                $fsOk = $currentPath !== '' && $fastspringClient->changeSubscriptionProduct($fsSubId, $currentPath, false);
            }
            if ($fsOk && $hasPendingCancel && ($resumeUser['cancel_method'] ?? '') === 'api') {
                $fsOk = $fastspringClient->uncancelSubscription($fsSubId);
            }
            if (!$fsOk) {
                echo json_encode(['ok' => false, 'error' => 'provider_api_failed']);
                exit;
            }
        } elseif ($needsApiCall && !$dodoSubId) {
            $subId = $resumeUser['paddle_subscription_id'] ?? null;
            if (!$subId || !$paddleClient->isConfigured() || !$paddleClient->resumeSubscription($subId)) {
                echo json_encode(['ok' => false, 'error' => 'paddle_api_failed']);
                exit;
            }
        }
        // For a 'manual' cancellation request there was never a real
        // Paddle-side schedule to undo — clearing our own flag is enough.
        // If it was actually already processed by support, the next
        // webhook call is what reflects reality anyway.
        $db->execute('UPDATE users SET cancel_requested_at = NULL, cancel_method = NULL, pending_plan_change = NULL WHERE id = ?', [$auth->userId()]);
        \App\Src\ActivityLog::record($db, $auth->userId(), 'cancellation_resumed', $dodoSubId ? 'dodo' : ($fsSubId ? 'fastspring' : 'paddle'), $resumeUser['plan_status'] ?? null, null, "user {$auth->userId()} resumed subscription / undid pending change");
        echo json_encode(['ok' => true]);
        exit;

    case 'change-subscription-plan':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $planKey = $_POST['plan'] ?? '';
        // Which Price to switch to: each plan can have a monthly and a
        // yearly Price in Paddle. Defaults to 'month' so existing callers
        // that don't send an interval keep working unchanged.
        $interval = $_POST['interval'] ?? 'month';
        if (!in_array($interval, ['month', 'year'], true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_interval']);
            exit;
        }
        $planPriceMap = [
            'starter' => ['month' => $config['paddle_starter_price_id'] ?? '', 'year' => $config['paddle_starter_yearly_price_id'] ?? ''],
            'pro' => ['month' => $config['paddle_pro_price_id'] ?? '', 'year' => $config['paddle_pro_yearly_price_id'] ?? ''],
            'active' => ['month' => $config['paddle_premium_price_id'] ?? '', 'year' => $config['paddle_premium_yearly_price_id'] ?? ''],
        ];
        $changeUser = $auth->currentUser();
        // Dodo/FastSpring subscribers switch products; Paddle subscribers switch prices.
        $dodoSubId = $changeUser['dodo_subscription_id'] ?? null;
        $fsSubId = $dodoSubId ? null : ($changeUser['fastspring_subscription_id'] ?? null);
        if ($dodoSubId) {
            $targetPriceId = $dodoBilling->productIdFor($planKey, $interval);
        } elseif ($fsSubId) {
            $targetPriceId = $fastspringBilling->pathFor($planKey, $interval);
        } else {
            $targetPriceId = $planPriceMap[$planKey][$interval] ?? '';
        }
        if ($targetPriceId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_plan']);
            exit;
        }
        $subId = $dodoSubId ?: ($fsSubId ?: ($changeUser['paddle_subscription_id'] ?? null));
        $providerReady = $dodoSubId ? $dodoClient->isConfigured() : ($fsSubId ? $fastspringClient->isConfigured() : $paddleClient->isConfigured());
        if (!empty($changeUser['cancel_requested_at'])) {
            // Paddle won't apply a price change on top of a subscription
            // that already has a pending cancellation scheduled — resuming
            // it first avoids a confusing Paddle API failure.
            echo json_encode(['ok' => false, 'error' => 'cancellation_pending']);
            exit;
        }
        if (!empty($changeUser['pending_plan_change'])) {
            // A previous downgrade is already scheduled for next billing
            // period — avoid stacking a second scheduled change.
            echo json_encode(['ok' => false, 'error' => 'change_pending']);
            exit;
        }
        if (!$auth->hasPaid() || ($changeUser['plan_status'] ?? '') === 'trial' || !$subId || !$providerReady) {
            // Can't safely swap the existing subscription in place — refuse
            // rather than fall back to opening a second checkout, which is
            // what caused double-billing before this fix.
            echo json_encode(['ok' => false, 'error' => 'manual_required']);
            exit;
        }
        $oldPlanStatus = $changeUser['plan_status'] ?? 'inactive';
        $oldInterval = ($changeUser['billing_interval'] ?? 'month') === 'year' ? 'year' : 'month';
        $oldRank = \App\Src\TokenManager::planRank($oldPlanStatus);
        $newRank = \App\Src\TokenManager::planRank($planKey);
        // Same tier, different billing cycle (e.g. Pro monthly -> Pro
        // yearly): not a downgrade, nothing to defer — apply it immediately
        // like an upgrade so billing_interval flips right away.
        $isSameTierIntervalSwitch = ($newRank === $oldRank) && ($interval !== $oldInterval);
        $isUpgrade = ($newRank > $oldRank) || $isSameTierIntervalSwitch;

        // Upgrades prorate now; downgrades bill the new price from the next rebill.
        if ($dodoSubId) {
            $success = $dodoClient->changeSubscriptionPlan($dodoSubId, $targetPriceId, $isUpgrade);
        } elseif ($fsSubId) {
            $success = $fastspringClient->changeSubscriptionProduct($fsSubId, $targetPriceId, $isUpgrade);
        } else {
            $success = $paddleClient->updateSubscriptionPrice($subId, $targetPriceId, $isUpgrade);
        }
        if (!$success) {
            echo json_encode(['ok' => false, 'error' => ($dodoSubId || $fsSubId) ? 'provider_api_failed' : 'paddle_api_failed']);
            exit;
        }
        \App\Src\ActivityLog::record(
            $db, $auth->userId(),
            $isUpgrade ? 'subscription_upgraded' : 'downgrade_scheduled',
            $dodoSubId ? 'dodo' : ($fsSubId ? 'fastspring' : 'paddle'),
            $planKey, $interval,
            "user {$auth->userId()} " . ($isUpgrade ? 'changed' : 'scheduled a change') . " {$oldPlanStatus}/{$oldInterval} -> {$planKey}/{$interval}"
        );

        // Paddle already accepted the price change at this point — a DB
        // error here must not surface as a broken response (or worse, look
        // like the whole change failed while Paddle already billed it).
        // Fall back to reporting success and let the next webhook call
        // reconcile plan_status/billing_interval from Paddle's own data.
        try {
            if ($isUpgrade) {
                // Upgrades apply immediately (Paddle charges the prorated
                // difference right now), so reflect it right away; the next
                // webhook call will reconcile from Paddle's own event data.
                $db->execute('UPDATE token_usage SET bonus_limit = 0 WHERE user_id = ?', [$auth->userId()]);
                $db->execute('UPDATE users SET plan_status = ?, has_paid = 1, billing_interval = ? WHERE id = ?', [$planKey, $interval, $auth->userId()]);
                echo json_encode(['ok' => true]);
            } else {
                // Downgrades are deferred to the next billing period (standard
                // practice — no immediate prorated credit). The user keeps
                // their current plan/quota until then; plan_status and the
                // token bonus only change once Paddle's webhook confirms the
                // new price actually took effect.
                $db->execute('UPDATE users SET pending_plan_change = ? WHERE id = ?', [$planKey, $auth->userId()]);
                echo json_encode(['ok' => true, 'deferred' => true]);
            }
        } catch (\Throwable $e) {
            error_log('change-subscription-plan: Paddle updated but local DB write failed: ' . $e->getMessage());
            echo json_encode(['ok' => true, 'reconciling' => true]);
        }
        exit;

    case 'request-refund':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $refundUser = $auth->currentUser();
        if (!$auth->hasPaid() || ($refundUser['plan_status'] ?? '') === 'trial') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'no_active_subscription']);
            exit;
        }
        if (!empty($refundUser['refund_requested_at'])) {
            echo json_encode(['ok' => true, 'already_requested' => true]);
            exit;
        }
        // Actually moving the money still goes through Paddle's dashboard —
        // Paddle is the merchant of record and handles refund compliance —
        // but the request itself is now tracked in-app and routed to
        // support immediately, instead of only existing as an email no one
        // in the product can see the status of.
        $db->execute('UPDATE users SET refund_requested_at = ' . $db->now() . ' WHERE id = ?', [$auth->userId()]);
        \App\Src\ActivityLog::record(
            $db, $auth->userId(), 'refund_requested',
            !empty($refundUser['dodo_subscription_id']) ? 'dodo' : (!empty($refundUser['fastspring_subscription_id']) ? 'fastspring' : 'paddle'),
            $refundUser['plan_status'] ?? null, null,
            "user {$auth->userId()} requested a refund"
        );
        if (!empty($config['mailtrap_api_token'])) {
            $mailer = new \App\Src\Mailer($config);
            $mailer->send(
                $config['mail_from_address'],
                'Refund request',
                '<p>User #' . (int)$auth->userId() . ' (' . htmlspecialchars($refundUser['email'] ?? '') . '), plan: ' . htmlspecialchars($refundUser['plan_status'] ?? '') . ', requested a refund. Check the payment date in the ' . (!empty($refundUser['dodo_subscription_id']) ? 'Dodo' : (!empty($refundUser['fastspring_subscription_id']) ? 'FastSpring' : 'Paddle')) . ' dashboard against the refund policy and process there.</p>'
            );
        }
        echo json_encode(['ok' => true]);
        exit;

    // ── GDPR self-service: export & delete ──────────────────────────
    case 'dodo-billing-portal':
        $requireAuth();
        $portalUser = $auth->currentUser();
        $dodoCustomerId = $portalUser['dodo_customer_id'] ?? null;
        if (!$dodoCustomerId || !$dodoClient->isConfigured()) {
            header('Location: ?page=dashboard');
            exit;
        }
        $portalUrl = $dodoClient->createCustomerPortalSession($dodoCustomerId, 'https://jumplearner.com/?page=dashboard');
        header('Location: ' . ($portalUrl ?: '?page=dashboard'));
        exit;

    case 'account-export':
        $requireAuth();
        $export = \App\Src\Account::export($db, $auth->userId());
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="jumplearner-my-data-' . date('Y-m-d') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case 'account-delete-confirm':
        $requireAuth();
        if (isset($_SESSION['account_delete_error'])) {
            $accountDeleteError = $_SESSION['account_delete_error'];
            unset($_SESSION['account_delete_error']);
        }
        require __DIR__ . '/../views/account-delete-confirm.php';
        break;

    case 'account-delete':
        $requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['account_delete_error'] = __('auth.error_generic');
            header('Location: ?page=account-delete-confirm');
            exit;
        }
        $deleteUser = $auth->currentUser();
        if (!password_verify($_POST['password'] ?? '', $deleteUser['password'] ?? '')) {
            $_SESSION['account_delete_error'] = __('account.wrong_password');
            header('Location: ?page=account-delete-confirm');
            exit;
        }
        \App\Src\Account::delete($db, $deleteUser, $dodoClient, $fastspringClient, $paddleClient);
        $auth->logout();
        header('Location: ?page=home&account_deleted=1');
        exit;

    case 'flashcards':
        $requirePlan();
        $currentUser = $auth->currentUser();
        // Deck language: ?lang= (remembered for the session) or the target
        // language. Studying another language never touches the profile.
        if (isset($_GET['lang']) && \App\Src\Language::isUsable(strtolower((string)$_GET['lang']), 'learn')) {
            $_SESSION['fc_lang'] = strtolower((string)$_GET['lang']);
        }
        $deckLang = $fcDeckLang();
        $flashcard->ensureStarterDeck($auth->userId(), $deckLang, $currentUser['native_lang'] ?? 'en');
        require __DIR__ . '/../views/flashcards.php';
        break;

    case 'flashcard-stats':
        $requireAuth();
        header('Content-Type: application/json');
        $fc = new \App\Src\Flashcard($db);
        echo json_encode($fc->getStats($auth->userId(), $fcDeckLang()));
        exit;

    case 'flashcard-due':
        $requireAuth();
        header('Content-Type: application/json');
        $fc = new \App\Src\Flashcard($db);
        $tab = $_GET['tab'] ?? 'due';
        $lang = $fcDeckLang();
        $userId = $auth->userId();

        if ($tab === 'due') {
            echo json_encode($fc->getDueCards($userId, $lang));
        } else {
            $view = in_array($tab, \App\Src\Flashcard::VIEWS, true) ? $tab : 'all';
            echo json_encode($fc->getAllCards($userId, $lang, $_GET['category'] ?? 'all', $_GET['q'] ?? '', 60, null, $view));
        }
        exit;

    case 'flashcard-review':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (isset($input['vocab_id'], $input['quality']) && csrf_verify(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
                $fc = new \App\Src\Flashcard($db);
                $result = $fc->reviewCard($auth->userId(), (int)$input['vocab_id'], (int)$input['quality']);
                echo json_encode($result);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;

    case 'flashcard-import':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
            $fc = new \App\Src\Flashcard($db);
            $user = $auth->currentUser();
            $result = $fc->importStaticCards($auth->userId(), $fcDeckLang(), $user['native_lang'] ?? 'en');
            echo json_encode(array_merge(['success' => true], $result));
            exit;
        }
        echo json_encode(['success' => false]);
        exit;

    case 'flashcard-card':
        // Create / edit / delete / favourite / learned for one card. JSON in
        // and out, CSRF-checked, never a 5xx (Cloudflare would eat the body).
        $requirePlan();
        header('Content-Type: application/json');
        $cardIn = json_decode(file_get_contents('php://input'), true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($cardIn) || !csrf_verify($cardIn['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'error' => 'invalid_request', 'message' => __('fc.err_generic')]);
            exit;
        }
        $fc = new \App\Src\Flashcard($db);
        $cardId = (int)($cardIn['id'] ?? 0);
        $fields = is_array($cardIn['card'] ?? null) ? $cardIn['card'] : [];
        switch ((string)($cardIn['action'] ?? '')) {
            case 'create':
                $r = $fc->createCard($auth->userId(), $fcDeckLang(), $fields);
                // A new card goes into the list that's open, else "Saved".
                if (!empty($r['card'])) {
                    $cl = new \App\Src\CardLists($db);
                    $into = (int)($cardIn['list_id'] ?? 0);
                    $into = $into && $cl->get($auth->userId(), $into) ? $into : $cl->defaultId($auth->userId(), $fcDeckLang());
                    $cl->add($auth->userId(), $into, [(int)$r['card']['id']]);
                    $r['card'] = $fc->getCard($auth->userId(), (int)$r['card']['id']);
                }
                break;
            case 'update':
                $r = $fc->updateCard($auth->userId(), $cardId, $fields);
                break;
            case 'delete':
                $r = $fc->deleteCard($auth->userId(), $cardId) ? ['deleted' => true] : ['error' => 'not_found'];
                break;
            case 'favorite':
                $c = $fc->setFavorite($auth->userId(), $cardId, !empty($cardIn['on']));
                $r = $c ? ['card' => $c] : ['error' => 'not_found'];
                break;
            case 'learned':
                $c = $fc->setLearned($auth->userId(), $cardId, !empty($cardIn['on']));
                $r = $c ? ['card' => $c] : ['error' => 'not_found'];
                break;
            default:
                $r = ['error' => 'invalid_request'];
        }
        if (!empty($r['error'])) {
            $key = 'fc.err_' . $r['error'];
            $msg = __($key);
            echo json_encode(['success' => false, 'error' => $r['error'], 'message' => $msg === $key ? __('fc.err_generic') : $msg] + array_intersect_key($r, ['existing_id' => 1]));
        } else {
            echo json_encode(['success' => true] + $r);
        }
        exit;

    // Word bank: every word we have for the deck language, each one a tap
    // away from the user's "Saved" list.
    case 'words':
        $requirePlan();
        $currentUser = $auth->currentUser();
        if (isset($_GET['lang']) && \App\Src\Language::isUsable(strtolower((string)$_GET['lang']), 'learn')) {
            $_SESSION['fc_lang'] = strtolower((string)$_GET['lang']);
        }
        $deckLang = $fcDeckLang();
        require __DIR__ . '/../views/words.php';
        break;

    case 'word-add':
        // JSON in and out, CSRF-checked, never a 5xx.
        $requirePlan();
        header('Content-Type: application/json');
        $wordIn = json_decode(file_get_contents('php://input'), true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($wordIn) || !csrf_verify($wordIn['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'error' => 'invalid_request', 'message' => __('fc.err_generic')]);
            exit;
        }
        $r = (new \App\Src\Flashcard($db))->addFromBank($auth->userId(), $fcDeckLang(), $auth->currentUser()['native_lang'] ?? 'en', (string)($wordIn['word'] ?? ''));
        if (!empty($r['card'])) {
            $cl = new \App\Src\CardLists($db);
            $cl->add($auth->userId(), $cl->defaultId($auth->userId(), $fcDeckLang()), [(int)$r['card']['id']]);
            echo json_encode(['success' => true, 'card_id' => (int)$r['card']['id']]);
        } else {
            $key = 'fc.err_' . ($r['error'] ?? 'generic');
            $msg = __($key);
            echo json_encode(['success' => false, 'error' => $r['error'] ?? 'generic', 'message' => $msg === $key ? __('fc.err_generic') : $msg]);
        }
        exit;

    case 'card-list':
        // Card lists (playlists): create / rename / delete a list, put cards
        // in (optionally moving them out of another list) or take them out.
        // JSON in and out, CSRF-checked, never a 5xx.
        $requirePlan();
        header('Content-Type: application/json');
        $listIn = json_decode(file_get_contents('php://input'), true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($listIn) || !csrf_verify($listIn['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'error' => 'invalid_request', 'message' => __('fc.err_generic')]);
            exit;
        }
        $cl = new \App\Src\CardLists($db);
        $listId = (int)($listIn['id'] ?? 0);
        $vocabIds = is_array($listIn['cards'] ?? null) ? $listIn['cards'] : [];
        switch ((string)($listIn['action'] ?? '')) {
            case 'create':
                $r = $cl->create($auth->userId(), $fcDeckLang(), (string)($listIn['name'] ?? ''));
                if (!empty($r['list']) && $vocabIds) {
                    $cl->add($auth->userId(), $r['list']['id'], $vocabIds, (int)($listIn['from'] ?? 0));
                    $r['list'] = $cl->get($auth->userId(), $r['list']['id']);
                }
                break;
            case 'rename':
                $r = $cl->rename($auth->userId(), $listId, (string)($listIn['name'] ?? ''));
                break;
            case 'delete':
                $r = $cl->delete($auth->userId(), $listId);
                break;
            case 'add':
                $r = $cl->add($auth->userId(), $listId, $vocabIds, (int)($listIn['from'] ?? 0));
                break;
            case 'remove':
                $r = $cl->remove($auth->userId(), $listId, $vocabIds);
                break;
            default:
                $r = ['error' => 'invalid_request'];
        }
        if (!empty($r['error'])) {
            $key = 'fc.err_' . $r['error'];
            $msg = __($key);
            echo json_encode(['success' => false, 'error' => $r['error'], 'message' => $msg === $key ? __('fc.err_generic') : $msg]);
        } else {
            echo json_encode(['success' => true] + $r);
        }
        exit;

    case 'flashcard-import-pack':
        // Adds one CEFR level of the extra vocabulary pack (data/vocab/) to the
        // user's deck. Responds with JSON only — never a 5xx (Cloudflare would
        // replace the body).
        $requirePlan();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            echo json_encode(['success' => false, 'error' => 'invalid_request']);
            exit;
        }
        $packUser = $auth->currentUser();
        $packResult = (new \App\Src\Flashcard($db))->importPack(
            $auth->userId(),
            $fcDeckLang(),
            $packUser['native_lang'] ?? 'en',
            (string)($_POST['level'] ?? '')
        );
        echo json_encode(array_merge(['success' => empty($packResult['error'])], $packResult));
        exit;

    // ── My mistakes ───────────────────────────────────────────────
    case 'mistakes':
        $requirePlan();
        $currentUser = $auth->currentUser();
        $mistakesLang = $currentUser['target_lang'] ?? 'en';
        $mistakesSvc = new \App\Src\Mistakes($db);
        $mistakesSvc->sync($auth->userId(), $mistakesLang);
        $mistakes = $mistakesSvc->getAll($auth->userId(), $mistakesLang);
        require __DIR__ . '/../views/mistakes.php';
        break;

    case 'mistake-review':
        // JSON only, never a 5xx (Cloudflare would replace the body).
        $requirePlan();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_request']);
            exit;
        }
        $reviewed = (new \App\Src\Mistakes($db))->review(
            $auth->userId(),
            (int)($_POST['id'] ?? 0),
            (string)($_POST['action'] ?? '')
        );
        echo json_encode($reviewed ? ['ok' => true, 'mistake' => $reviewed] : ['ok' => false, 'error' => 'not_found']);
        exit;

    // ── Dashboard ─────────────────────────────────────────────────
    case 'dashboard':
        $requirePlan();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_preferences'])) {
            $n_lang = Auth::pick($_POST['native_lang'] ?? null, Language::supportedLangs(), 'en');
            $c_lvl  = Auth::pick($_POST['cefr_level'] ?? null, Auth::CEFR_LEVELS, 'A1');
            $dashCurrentUser = $auth->currentUser();
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $_SESSION['pref_error'] = __('auth.error_generic');
            } elseif ($n_lang === ($dashCurrentUser['target_lang'] ?? '')) {
                $_SESSION['pref_error'] = __('onboarding.same_lang_error');
            } else {
                // This picker is labelled "interface language": it sets the UI
                // language as well as the native language used in translations.
                $uiPick = Language::isUsable($n_lang, 'ui') ? $n_lang : null;
                $db->execute('UPDATE users SET native_lang = ?, ui_lang = ?, cefr_level = ? WHERE id = ?', [$n_lang, $uiPick, $c_lvl, $auth->userId()]);
                if ($uiPick) {
                    setcookie(Language::COOKIE, $uiPick, ['expires' => time() + 86400 * 365, 'path' => '/', 'secure' => $isHttps, 'httponly' => false, 'samesite' => 'Lax']);
                }
                $_SESSION['pref_saved'] = true;
            }
            header('Location: ?page=dashboard');
            exit;
        }

        // Keeps the dashboard's "review your mistakes" count current; must
        // never take the dashboard down with it.
        try {
            (new \App\Src\Mistakes($db))->sync($auth->userId(), $auth->currentUser()['target_lang'] ?? 'en');
        } catch (\Throwable $e) {
            error_log('dashboard: mistakes sync failed: ' . $e->getMessage());
        }

        // Compute quota for dashboard
        $tokenManager = new \App\Src\TokenManager($db);
        $quotaRemaining = $tokenManager->getRemaining($auth->userId());
        $dashUser = $auth->currentUser();
        $dashPlanStatus = $dashUser['plan_status'] ?? 'inactive';
        $quotaTotal = $tokenManager->getBaseLimit($dashPlanStatus);
        $tokenUsage = $db->fetchOne('SELECT bonus_limit FROM token_usage WHERE user_id = ?', [$auth->userId()]);
        $quotaTotal += ($tokenUsage ? (int)$tokenUsage['bonus_limit'] : 0);

        require __DIR__ . '/../views/dashboard.php';
        break;

    // ── Chat ──────────────────────────────────────────────────────
    case 'chat':
                $requirePlan();
        // Ensure JSON response only for AJAX requests (chat history loading)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['conv_id'])) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input  = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!csrf_verify(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
                header('Content-Type: application/json');
                echo json_encode(['error' => __('error.session_expired')]);
                exit;
            }
            $userId = $auth->userId();
            $msg    = trim($input['message'] ?? '');
            $convId = isset($input['conversationId']) ? (int)$input['conversationId'] : null;
            $topic  = $input['topicId'] ?? null;

            if ($msg === '') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Message cannot be empty.']);
                exit;
            }

            $currentUser = $auth->currentUser();
            $isTrial = (($currentUser['plan_status'] ?? '') === 'trial');
            if ($isTrial && $auth->getTrialMessagesSent($userId) >= 15) {
                header('Content-Type: application/json');
                echo json_encode(['error' => __('error.trial_expired')]);
                exit;
            }

            $result = $chat->handleMessage($userId, $msg, $gemini, $convId, $topic);
            $result['isTrial'] = $isTrial;
            if ($isTrial) {
                $sent = $auth->getTrialMessagesSent($userId);
                $result['trialRemaining'] = max(0, 15 - $sent);
            }

            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        // AJAX: get conversation messages
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['conv_id']) &&
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            $msgs = $chat->getMessages($auth->userId(), (int)$_GET['conv_id']);
            header('Content-Type: application/json');
            echo json_encode(['messages' => $msgs]);
            exit;
        }

        $conversations = $chat->getConversations($auth->userId());
        $currentUser   = $auth->currentUser();

        // Compute quota for initial page load
        $tokenManager = new \App\Src\TokenManager($db);
        $quotaRemaining = $tokenManager->getRemaining($auth->userId());
        $planStatus = $currentUser['plan_status'] ?? 'inactive';
        $quotaTotal = $tokenManager->getBaseLimit($planStatus);
        $tokenUsage = $db->fetchOne('SELECT bonus_limit FROM token_usage WHERE user_id = ?', [$auth->userId()]);
        $quotaTotal += ($tokenUsage ? (int)$tokenUsage['bonus_limit'] : 0);

        require __DIR__ . '/../views/chat.php';
        break;

    // ── Blog ──────────────────────────────────────────────────────
    case 'blog':
        $posts = $db->fetchAll("SELECT id,title,slug,created_at FROM posts WHERE published=1 AND category='blog' ORDER BY created_at DESC LIMIT 20");
        require __DIR__ . '/../views/blog/list.php';
        break;

    case 'post':
        $slug = $_GET['slug'] ?? '';
        $post = $db->fetchOne('SELECT * FROM posts WHERE slug=? AND published=1', [$slug]);
        if (!$post) { header('Location: ?page=blog'); exit; }
        require __DIR__ . '/../views/blog/post.php';
        break;

    // ── Admin panel routes ──────────────────────────────────────
    case 'admin-login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->handleLogin($_POST);
        } else {
            $adminCtrl->showLogin();
        }
        break;
    case 'admin-logout':
        $adminCtrl->logout();
        break;
    case 'admin-dashboard':
        $adminCtrl->dashboard();
        break;
    case 'admin-users':
        $adminCtrl->listUsers($_GET);
        break;
    case 'admin-user':
        $adminCtrl->userDetail((int)($_GET['id'] ?? 0));
        break;
    case 'admin-user-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->userAction($_POST);
        }
        header('Location: ?page=admin-users'); exit;
    case 'admin-admins':
        $adminCtrl->listAdmins();
        break;
    case 'admin-create-admin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->createAdmin($_POST);
        }
        header('Location: ?page=admin-admins'); exit;
    case 'admin-delete-admin':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->deleteAdmin((int)($_POST['id'] ?? 0));
        }
        header('Location: ?page=admin-admins'); exit;
    case 'admin-payments':
        $adminCtrl->revenue($_GET);
        break;
    case 'admin-revenue-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->revenueAction($_POST);
        }
        header('Location: ?page=admin-payments'); exit;
    case 'admin-activity':
        $adminCtrl->listActivity($_GET);
        break;
    case 'admin-conversations':
        $adminCtrl->listConversations($_GET);
        break;
    case 'admin-conversation':
        $adminCtrl->viewConversation((int)($_GET['conv_id'] ?? 0));
        break;
    case 'admin-ai-usage':
        $adminCtrl->aiUsage();
        break;
    case 'admin-settings':
        $adminCtrl->settings();
        break;
    case 'admin-health':
        $adminCtrl->health();
        break;
    case 'admin-audit':
        $adminCtrl->auditLog($_GET);
        break;
    case 'admin-2fa':
        $adminCtrl->twoFactor();
        break;
    case 'admin-2fa-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->twoFactorAction($_POST);
        }
        header('Location: ?page=admin-2fa'); exit;
    case 'admin-login-2fa':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->handleLogin2fa($_POST);
        } else {
            $adminCtrl->showLogin2fa();
        }
        break;
    case 'admin-lang':
        $adminCtrl->setAdminLang((string)($_GET['lang'] ?? ''));
        break;
    case 'admin-languages':
        $adminCtrl->languages();
        break;
    case 'admin-languages-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->languagesAction($_POST);
        }
        header('Location: ?page=admin-languages'); exit;
    case 'admin-language-translate':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->languageTranslate($_POST);
        }
        http_response_code(405); exit;
    case 'admin-language-strings':
        $adminCtrl->languageStrings($_GET);
        break;
    case 'admin-language-string-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->languageStringAction($_POST);
        }
        header('Location: ?page=admin-languages'); exit;
    case 'admin-language-export':
        $adminCtrl->languageExport((string)($_GET['code'] ?? ''));
        break;
    case 'admin-lexicon':
        $adminCtrl->lexicon($_GET);
        break;
    case 'admin-lexicon-entry':
        $adminCtrl->lexiconEntry((int)($_GET['id'] ?? 0));
        break;
    case 'admin-lexicon-action':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->lexiconAction($_POST);
        }
        header('Location: ?page=admin-lexicon'); exit;
    case 'admin-export':
        $adminCtrl->exportCsv($_GET['type'] ?? '');
        break;

    // ── Alphabet progress (AJAX) ────────────────────────────────────
    case 'alphabet-progress':
        $requireAuth();
        header('Content-Type: application/json');
        $lang = $_GET['lang'] ?? 'en';
        $rows = $db->fetchAll('SELECT letter_key FROM alphabet_progress WHERE user_id = ? AND lang = ?', [$auth->userId(), $lang]);
        echo json_encode(['learned' => array_column($rows, 'letter_key')]);
        exit;

    case 'alphabet-toggle':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'invalid_request']);
            exit;
        }
        $lang = $_POST['lang'] ?? 'en';
        $letterKey = trim($_POST['letter_key'] ?? '');
        if ($letterKey === '') {
            http_response_code(400);
            echo json_encode(['error' => 'missing_letter_key']);
            exit;
        }
        $existing = $db->fetchOne('SELECT id FROM alphabet_progress WHERE user_id = ? AND lang = ? AND letter_key = ?', [$auth->userId(), $lang, $letterKey]);
        if ($existing) {
            $db->execute('DELETE FROM alphabet_progress WHERE id = ?', [$existing['id']]);
            echo json_encode(['learned' => false]);
        } else {
            $db->execute('INSERT INTO alphabet_progress (user_id, lang, letter_key) VALUES (?, ?, ?)', [$auth->userId(), $lang, $letterKey]);
            echo json_encode(['learned' => true]);
        }
        exit;

    // ── Static pages ──────────────────────────────────────────────
    case 'chat-tips':
    case 'privacy-policy':
    case 'terms-and-conditions':
    case 'refund-policy':
    case 'license-agreement':
    case 'cookie-policy':
    case 'about':
    case 'contact':
    case 'faq':
    case 'alphabet':
        $allowed = ['chat-tips','privacy-policy','terms-and-conditions','refund-policy','license-agreement','cookie-policy','about','contact','faq','alphabet'];
        if (in_array($page, $allowed, true)) {
            require __DIR__ . '/../views/pages/' . $page . '.php';
        }
        break;

    case 'home':
        require __DIR__ . '/../views/home.php';
        break;

    default:
        // An unrecognized ?page= (or clean URL) used to silently render the
        // homepage with a 200 — a "soft 404" that search engines index as
        // real content and that gives a dead link no visible signal it's
        // dead. Same visual fallback, correct status code.
        http_response_code(404);
        require __DIR__ . '/../views/home.php';
}
?>
