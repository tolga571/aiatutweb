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

// Initialize language system
$detectedLang = 'en';
if ($auth->isLoggedIn()) {
    $currentUser = $auth->currentUser();
    // Use user's language preference only after onboarding is completed
    if (!empty($currentUser['onboarding_completed'])) {
        $detectedLang = $currentUser['native_lang'] ?? $currentUser['target_lang'] ?? 'en';
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
    if (!$auth->hasPaid())    { header('Location: ?page=pricing'); exit; }
};

switch ($page) {

    // ── Auth ─────────────────────────────────────────────────────
    case 'login':
        if (isset($_SESSION['login_error'])) {
            $loginError = $_SESSION['login_error'];
            unset($_SESSION['login_error']);
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
            if ($auth->tooManyAttempts($clientIp, 'register', 5, 3600)) {
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
                        $redirect = isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '';
                        header('Location: ?page=onboarding' . $redirect); exit;
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
            
            $data = null;
            
            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->get('https://oauth2.googleapis.com/tokeninfo', [
                    'query' => ['id_token' => $credential]
                ]);
                
                $decoded = json_decode($response->getBody()->getContents(), true);
                if ($decoded && isset($decoded['aud']) && $decoded['aud'] === $googleClientId) {
                    $data = $decoded;
                }
            } catch (\Exception $e) {
                // Verification failed
            }
            
            if ($data) {
                $googleId = $data['sub'] ?? '';
                $email = trim($data['email'] ?? '');
                $name = trim($data['name'] ?? '');
                $picture = trim($data['picture'] ?? '');
                
                if (!empty($email)) {
                    // Check if user exists by google_id
                    $user = $db->fetchOne('SELECT * FROM users WHERE google_id = ?', [$googleId]);
                    
                    if (!$user) {
                        // Check if user exists by email
                        $user = $db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
                        
                        if ($user) {
                            // User exists, link Google ID and optionally picture
                            $db->execute(
                                'UPDATE users SET google_id = ?, profile_image = COALESCE(profile_image, ?) WHERE id = ?',
                                [$googleId, $picture ?: null, $user['id']]
                            );
                        } else {
                            // User does not exist, register new user
                            $randomPassword = bin2hex(random_bytes(16));
                            $hash = password_hash($randomPassword, PASSWORD_BCRYPT);
                            
                            $db->execute(
                                'INSERT INTO users (email, password, name, google_id, profile_image) VALUES (?, ?, ?, ?, ?)',
                                [$email, $hash, $name, $googleId, $picture ?: null]
                            );
                            
                            $user = $db->fetchOne('SELECT * FROM users WHERE google_id = ?', [$googleId]);
                        }
                    } else {
                        // If picture is updated or wasn't set, update it
                        if (!empty($picture) && $user['profile_image'] !== $picture) {
                            $db->execute('UPDATE users SET profile_image = ? WHERE id = ?', [$picture, $user['id']]);
                        }
                    }
                    
                    // Log user in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    
                    // Update streak / activity date
                    $today = date('Y-m-d');
                    $lastActivity = $user['last_activity_date'] ?? null;
                    
                    if ($lastActivity !== $today) {
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $streak = (int)($user['streak_count'] ?? 0);
                        
                        if ($lastActivity === $yesterday) {
                            $streak++;
                        } else {
                            $streak = 1;
                        }
                        
                        $db->execute(
                            'UPDATE users SET streak_count = ?, last_activity_date = ? WHERE id = ?',
                            [$streak, $today, $user['id']]
                        );
                    }
                    
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
            $native = $_POST['native_lang'] ?? 'en';
            $target = $_POST['target_lang'] ?? 'en';
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
        if (in_array($lang, ['en','de','fr','es','zh','ja','ar','tr'])) {
            $currentUser = $auth->currentUser();
            if ($lang === ($currentUser['native_lang'] ?? '')) {
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
        $curr = $auth->currentUser();
        if (($curr['plan_status'] ?? 'inactive') === 'inactive') {
            $db->execute('UPDATE users SET plan_status = ? WHERE id = ?', ['trial', $auth->userId()]);
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
        $exportUserId = $auth->userId();
        $exportUser = $auth->currentUser();
        unset($exportUser['password']);
        $export = [
            'exported_at' => date('c'),
            'profile' => $exportUser,
            'conversations' => $db->fetchAll('SELECT id, topic_id, topic_label, created_at, updated_at FROM conversations WHERE user_id = ? ORDER BY created_at', [$exportUserId]),
            'messages' => $db->fetchAll(
                'SELECT m.id, m.conversation_id, m.role, m.content, m.translation, m.correction, m.created_at
                 FROM messages m JOIN conversations c ON c.id = m.conversation_id
                 WHERE c.user_id = ? ORDER BY m.created_at',
                [$exportUserId]
            ),
            'vocabulary_words' => $db->fetchAll('SELECT * FROM vocabulary_words WHERE user_id = ?', [$exportUserId]),
            'flashcards' => $db->fetchAll('SELECT * FROM user_flashcards WHERE user_id = ?', [$exportUserId]),
            'alphabet_progress' => $db->fetchAll('SELECT * FROM alphabet_progress WHERE user_id = ?', [$exportUserId]),
            'learning_notes' => $db->fetchAll('SELECT * FROM learning_notes WHERE user_id = ?', [$exportUserId]),
            'billing_activity' => $db->fetchAll(
                'SELECT event_type, provider, plan, billing_interval, detail, created_at FROM activity_events WHERE user_id = ? ORDER BY created_at',
                [$exportUserId]
            ),
        ];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="aitut-my-data-' . date('Y-m-d') . '.json"');
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
        $deleteUserId = $auth->userId();
        // Best-effort: stop future billing before erasing the account a
        // webhook would otherwise try to reconcile against. Not blocking —
        // the right to erasure doesn't wait on a provider API call.
        if (!empty($deleteUser['dodo_subscription_id']) && $dodoClient->isConfigured()) {
            $dodoClient->cancelSubscription($deleteUser['dodo_subscription_id']);
        } elseif (!empty($deleteUser['fastspring_subscription_id']) && $fastspringClient->isConfigured()) {
            $fastspringClient->cancelSubscription($deleteUser['fastspring_subscription_id']);
        } elseif (!empty($deleteUser['paddle_subscription_id']) && $paddleClient->isConfigured()) {
            $paddleClient->cancelSubscription($deleteUser['paddle_subscription_id'], 'immediately');
        }
        \App\Src\ActivityLog::record($db, $deleteUserId, 'account_deleted', null, $deleteUser['plan_status'] ?? null, null, "user {$deleteUserId} ({$deleteUser['email']}) deleted their own account");
        // token_usage has no ON DELETE CASCADE from users — every other
        // user-owned table does, so this is the only manual cleanup needed
        // before the DELETE below can succeed.
        $db->execute('DELETE FROM token_usage WHERE user_id = ?', [$deleteUserId]);
        $db->execute('DELETE FROM users WHERE id = ?', [$deleteUserId]);
        $auth->logout();
        header('Location: ?page=home&account_deleted=1');
        exit;

    case 'flashcards':
        $requirePlan();
        $currentUser = $auth->currentUser();
        // Auto-import static cards if user has none for their target language
        $vocabCount = $db->fetchOne(
            'SELECT COUNT(*) as c FROM vocabulary_words WHERE user_id = ? AND language = ?',
            [$auth->userId(), $currentUser['target_lang'] ?? 'en']
        );
        if ((int)($vocabCount['c'] ?? 0) < 50) {
            $flashcard->importStaticCards(
                $auth->userId(),
                $currentUser['target_lang'] ?? 'en',
                $currentUser['native_lang'] ?? 'en'
            );
        }
        require __DIR__ . '/../views/flashcards.php';
        break;

    case 'flashcard-stats':
        $requireAuth();
        header('Content-Type: application/json');
        $fc = new \App\Src\Flashcard($db);
        echo json_encode($fc->getStats($auth->userId(), $auth->currentUser()['target_lang'] ?? 'en'));
        exit;

    case 'flashcard-due':
        $requireAuth();
        header('Content-Type: application/json');
        $fc = new \App\Src\Flashcard($db);
        $tab = $_GET['tab'] ?? 'due';
        $lang = $auth->currentUser()['target_lang'] ?? 'en';
        $userId = $auth->userId();
        
        if ($tab === 'due') {
            echo json_encode($fc->getDueCards($userId, $lang));
        } elseif ($tab === 'chat') {
            echo json_encode($fc->getChatWords($userId, $lang));
        } else {
            $cat = $_GET['category'] ?? 'all';
            $search = $_GET['q'] ?? '';
            echo json_encode($fc->getAllCards($userId, $lang, $cat, $search));
        }
        exit;

    case 'flashcard-review':
        $requireAuth();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (isset($input['vocab_id'], $input['quality'])) {
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fc = new \App\Src\Flashcard($db);
            $user = $auth->currentUser();
            $result = $fc->importStaticCards($auth->userId(), $user['target_lang'] ?? 'en', $user['native_lang'] ?? 'en');
            echo json_encode(array_merge(['success' => true], $result));
            exit;
        }
        echo json_encode(['success' => false]);
        exit;

    // ── Dashboard ─────────────────────────────────────────────────
    case 'dashboard':
        $requirePlan();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_preferences'])) {
            $n_lang = $_POST['native_lang'] ?? 'en';
            $c_lvl  = $_POST['cefr_level'] ?? 'A1';
            $dashCurrentUser = $auth->currentUser();
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $_SESSION['pref_error'] = __('auth.error_generic');
            } elseif ($n_lang === ($dashCurrentUser['target_lang'] ?? '')) {
                $_SESSION['pref_error'] = __('onboarding.same_lang_error');
            } else {
                $db->execute('UPDATE users SET native_lang = ?, cefr_level = ? WHERE id = ?', [$n_lang, $c_lvl, $auth->userId()]);
                $_SESSION['pref_saved'] = true;
            }
            header('Location: ?page=dashboard');
            exit;
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
        $adminCtrl->listUsers((int)($_GET['p'] ?? 1), (string)($_GET['q'] ?? ''));
        break;
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
        $adminCtrl->listPayments((int)($_GET['p'] ?? 1), (string)($_GET['q'] ?? ''));
        break;
    case 'admin-activity':
        $adminCtrl->listActivity((int)($_GET['p'] ?? 1));
        break;
    case 'admin-conversations':
        $adminCtrl->listConversations();
        break;
    case 'admin-conversation':
        $adminCtrl->viewConversation((int)($_GET['conv_id'] ?? 0));
        break;
    case 'admin-settings':
        $adminCtrl->settings();
        break;
    case 'admin-update-settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $adminCtrl->updateSettings($_POST);
        }
        break;
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
