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
 * Returns the current CSRF token, generating one for this session if needed.
 */
function csrf_token(): string
{
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
 * Verifies a submitted token against the session token using a timing-safe comparison.
 */
function csrf_verify(?string $submitted): bool
{
    if (empty($_SESSION['csrf_token']) || empty($submitted)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Best-effort client IP, honoring the X-Forwarded-For header set by
 * Railway's proxy (falls back to REMOTE_ADDR when absent/local).
 */
function client_ip(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
