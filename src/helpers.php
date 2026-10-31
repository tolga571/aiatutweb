<?php

use App\Src\Language;

/**
 * Global translation helper function.
 * Translates a key using the currently loaded language.
 */
function __(string $key, string $default = ''): string
{
    return Language::get($key, $default !== '' ? $default : $key);
}

/**
 * Translation with placeholders: t('fc.cards_due', ['n' => 5]) replaces {n}.
 * Same strings as __(); the JavaScript twin is window.t() (views/partials/head.php).
 */
function t(string $key, array $vars = []): string
{
    $s = Language::get($key, $key);
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

/**
 * CSRF tokens. Signed-in users keep theirs in the session, as before.
 * Visitors get a signed double-submit token instead: a random cookie plus
 * an HMAC of it in the form. Keeping visitors' tokens out of the session
 * means a crawler opening /login no longer writes a sessions row (they had
 * piled up to ~100k a day).
 */
const CSRF_COOKIE = 'jl_csrf';

function csrf_secret(): string
{
    static $secret = null;
    // Server-only value; no extra config or query needed.
    return $secret ??= hash('sha256', 'jl-csrf|' . (getenv('DATABASE_URL') ?: 'local'), true);
}

/** Call once per request before any output: gives a visitor the CSRF cookie if they have none. */
function csrf_boot(bool $https): void
{
    $c = $_COOKIE[CSRF_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $c)) {
        $c = bin2hex(random_bytes(32));
        setcookie(CSRF_COOKIE, $c, ['expires' => time() + 86400 * 7, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[CSRF_COOKIE] = $c;
    }
}

function csrf_guest_token(): string
{
    $c = $_COOKIE[CSRF_COOKIE] ?? '';
    return preg_match('/^[a-f0-9]{64}$/', $c) ? hash_hmac('sha256', $c, csrf_secret()) : '';
}

/**
 * Returns the CSRF token for forms: the session's for signed-in users (or
 * when the session already has one), otherwise the visitor token.
 */
function csrf_token(): string
{
    $signedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);
    if (empty($_SESSION['csrf_token']) && !$signedIn && ($guest = csrf_guest_token()) !== '') {
        return $guest;
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Renders a hidden input carrying the CSRF token, for use inside <form> tags.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifies a submitted token against the session token or the visitor
 * token, using timing-safe comparisons.
 */
function csrf_verify(?string $submitted): bool
{
    if (empty($submitted)) {
        return false;
    }
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted)) {
        return true;
    }
    $guest = csrf_guest_token();
    return $guest !== '' && hash_equals($guest, $submitted);
}

/**
 * Cloudflare's published edge ranges (cloudflare.com/ips-v4, /ips-v6,
 * checked 2026-10-02). Only a request whose Railway peer is one of these
 * actually came through Cloudflare, so only then is CF-Connecting-IP
 * trustworthy.
 */
const CLOUDFLARE_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
    '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
    '2a06:98c0::/29', '2c0f:f248::/32',
];

/** True if $ip (v4 or v6) is inside $cidr. */
function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $bits = $bits === null ? strlen($ipBin) * 8 : (int)$bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xff << (8 - $rest)) & 0xff;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}

/** The visitor's country as Cloudflare sees it (CF-IPCountry header), '' when unknown (e.g. locally). */
function visitor_country(): string
{
    $c = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    return preg_match('/^[A-Z]{2}$/', $c) ? $c : '';
}

/** True when sales are closed to the visitor's country (config sales_blocked_countries). */
function sales_closed_here(array $config): bool
{
    $c = visitor_country();
    return $c !== '' && in_array($c, $config['sales_blocked_countries'] ?? [], true);
}

/**
 * The client's IP, for rate limiting and audit logs.
 *
 * X-Forwarded-For used to be trusted here, but its first entry is whatever
 * the client sends, so anyone could dodge the login limits by sending a new
 * value per request. Now: Railway's edge sets X-Real-IP to the peer that
 * connected to it. If that peer is Cloudflare, the real client is in
 * CF-Connecting-IP (set by Cloudflare); otherwise the request reached
 * Railway directly and the peer *is* the client. Locally (no Railway edge)
 * this falls back to REMOTE_ADDR.
 */
function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $peer = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if (!filter_var($peer, FILTER_VALIDATE_IP)) {
        // Fallback if X-Real-IP is ever absent: when we're behind a private
        // proxy, the *last* X-Forwarded-For entry is the one that proxy
        // appended (the client only controls the ones before it). Without
        // this, every visitor would share the proxy's address and one
        // person's failed logins would lock everybody out.
        // GLOBAL_RANGE (PHP 8.2+) also treats Railway's 100.64.0.0/10 as non-public.
        $remoteIsPrivate = filter_var($remote, FILTER_VALIDATE_IP) && !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE);
        $xff = array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        $last = end($xff);
        if ($remoteIsPrivate && filter_var($last, FILTER_VALIDATE_IP) && $remote !== '127.0.0.1') {
            $peer = $last;
        } else {
            return $remote ?: 'unknown';
        }
    }
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        foreach (CLOUDFLARE_RANGES as $range) {
            if (ip_in_cidr($peer, $range)) {
                return $cf;
            }
        }
    }
    return $peer;
}

/**
 * A translation (or any text) for use inside a JavaScript string or template
 * literal in a view: '…<?= jsq(__('x')) ?>…'. Translations are raw text, so an
 * apostrophe ("l'activation") would otherwise end the string and break every
 * script on the page. Markup inside the text still works with innerHTML.
 */
function jsq(string $s): string
{
    $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    return str_replace(['`', '${'], ['\\u0060', '\\u0024{'], substr((string)$json, 1, -1));
}

/**
 * Whether $key is a letter of $lang's alphabet that can be marked as learned
 * (data/alphabets.php; the key is the letter's "upper" or "char", the same
 * value the website and the app send).
 */
function alphabet_letter_valid(string $lang, string $key): bool
{
    static $alphabets = null;
    $alphabets ??= require __DIR__ . '/../data/alphabets.php';
    foreach ($alphabets[$lang]['letters'] ?? [] as $letter) {
        if (($letter['upper'] ?? $letter['char'] ?? null) === $key) {
            return true;
        }
    }
    return false;
}
