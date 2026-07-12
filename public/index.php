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

$page = $_GET['page'] ?? 'home';

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
            if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                $loginError = __('auth.error_generic');
            } else {
                $email = trim($_POST['email'] ?? '');
                $pass  = $_POST['password'] ?? '';
                if ($auth->login($email, $pass)) {
                    $redirect = $_GET['redirect'] ?? 'dashboard';
                    header('Location: ?page=' . urlencode($redirect)); exit;
                }
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
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $pass_confirm = $_POST['password_confirm'] ?? '';
            $name  = trim($_POST['name'] ?? '');
            $terms = isset($_POST['terms']);
            $errors = [];
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
                    $auth->login($email, $pass);
                    $redirect = isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : '';
                    header('Location: ?page=onboarding' . $redirect); exit;
                }
                $errors[] = __('auth.registration_failed');
            }
            $registerError = $errors;
        }
        require __DIR__ . '/../views/register.php';
        break;

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
        $db->execute('UPDATE users SET payment_pending_at = ' . $db->now() . ' WHERE id = ?', [$auth->userId()]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;

    case 'check-payment-status':
        $requireAuth();
        header('Content-Type: application/json');

        $userId = $auth->userId();
        $paid = $auth->hasPaid();

        if (!$paid) {
            $user = $db->fetchOne('SELECT payment_pending_at, plan_status FROM users WHERE id = ?', [$userId]);
            if (!empty($user['payment_pending_at'])) {
                $pendingTime = strtotime($user['payment_pending_at']);
                $elapsed = time() - $pendingTime;
                if ($elapsed > 60) {
                    $db->execute('UPDATE users SET plan_status = ?, has_paid = 1, payment_pending_at = NULL WHERE id = ?', ['active', $userId]);
                    $paid = true;
                }
            }
        }

        echo json_encode(['paid' => $paid]);
        exit;

    case 'start-trial':
        $requireAuth();
        $curr = $auth->currentUser();
        if (($curr['plan_status'] ?? 'inactive') === 'inactive') {
            $db->execute('UPDATE users SET plan_status = ? WHERE id = ?', ['trial', $auth->userId()]);
        }
        header('Location: ?page=chat');
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
            if ($isTrial && $auth->getTrialMessagesSent($userId) >= 5) {
                header('Content-Type: application/json');
                echo json_encode(['error' => __('error.trial_expired')]);
                exit;
            }

            $result = $chat->handleMessage($userId, $msg, $gemini, $convId, $topic);
            $result['isTrial'] = $isTrial;
            if ($isTrial) {
                $sent = $auth->getTrialMessagesSent($userId);
                $result['trialRemaining'] = max(0, 5 - $sent);
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
        $adminCtrl->listUsers();
        break;
    case 'admin-admins':
        $adminCtrl->listAdmins();
        break;
    case 'admin-payments':
        $adminCtrl->listPayments();
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

    default:
        require __DIR__ . '/../views/home.php';
}
?>
