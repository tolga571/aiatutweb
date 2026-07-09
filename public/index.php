<?php
require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use App\Src\Database;
use App\Src\Auth;
use App\Src\Chat;
use App\Src\GeminiClient;
use App\Src\AdminController;
use App\Src\Language;
use App\Src\Flashcard;

session_start();

$db     = new Database($config['db_path'], $config['db_url']);
$auth   = new Auth($db);
$chat   = new Chat($db, $config);
$gemini = new GeminiClient($config['gemini_api_key']);
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
$requireAuth = function() use ($auth) {
    if (!$auth->isLoggedIn()) {
        header('Location: ?page=login');
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
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            if ($auth->login($email, $pass)) {
                header('Location: ?page=dashboard'); exit;
            }
            $loginError = $auth->lastError ?: __('auth.error_generic');
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
            $name  = trim($_POST['name'] ?? '');
            $errors = [];
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('auth.error_invalid_email');
            }
            if (empty($name) || mb_strlen($name) < 2) {
                $errors[] = __('auth.error_invalid_name');
            }
            if (strlen($pass) < 8) {
                $errors[] = __('auth.error_weak_password');
            }
            if (empty($errors)) {
                if ($auth->register($email, $pass, $name)) {
                    $auth->login($email, $pass);
                    header('Location: ?page=onboarding'); exit;
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
                        header('Location: ?page=dashboard');
                    } else {
                        header('Location: ?page=onboarding');
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
            header('Location: ?page=dashboard'); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $native = $_POST['native_lang'] ?? 'en';
            $target = $_POST['target_lang'] ?? 'en';
            if ($native === $target) {
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
                header('Location: ?page=dashboard'); exit;
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
        $requireAuth();
        $currentUser = $auth->currentUser();
        require __DIR__ . '/../views/pricing.php';
        break;

    case 'check-payment-status':
        $requireAuth();
        header('Content-Type: application/json');
        echo json_encode(['paid' => $auth->hasPaid()]);
        exit;

    case 'start-trial':
        $requireAuth();
        $db->execute('UPDATE users SET plan_status = ? WHERE id = ?', ['trial', $auth->userId()]);
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

            try {
                $result = $chat->handleMessage($userId, $msg, $gemini, $convId, $topic);
                $result['isTrial'] = $isTrial;
                if ($isTrial) {
                    $sent = $auth->getTrialMessagesSent($userId);
                    $result['trialRemaining'] = max(0, 5 - $sent);
                }
            } catch (\Throwable $e) {
                $result = ['error' => 'AI unavailable, please try again.'];
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
        require __DIR__ . '/../views/chat.php';
        break;

    // ── Blog ──────────────────────────────────────────────────────
    case 'blog':
        $posts = $db->fetchAll('SELECT id,title,slug,created_at FROM posts WHERE published=1 AND category="blog" ORDER BY created_at DESC LIMIT 20');
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
        $allowed = ['chat-tips','privacy-policy','terms-and-conditions','refund-policy','license-agreement','cookie-policy','about','contact','faq'];
        if (in_array($page, $allowed, true)) {
            require __DIR__ . '/../views/pages/' . $page . '.php';
        }
        break;

    // ── Temporary: Create admin ─────────────────────────────
    case 'create-admin':
        header('Content-Type: text/plain');
        $existing = $db->fetchOne('SELECT id FROM admins WHERE email = ?', ['admin@example.com']);
        if ($existing) {
            echo "Admin already exists (id={$existing['id']}).\n";
            exit;
        }
        $hash = password_hash('12345678', PASSWORD_DEFAULT);
        $db->execute('INSERT INTO admins (email, password, name, created_at) VALUES (?, ?, ?, NOW())', ['admin@example.com', $hash, 'Admin']);
        echo "Admin created!\nEmail: admin@example.com\nPassword: 12345678\n";
        echo "Login: ?page=admin-login\n";
        exit;

    default:
        require __DIR__ . '/../views/home.php';
}
?>
