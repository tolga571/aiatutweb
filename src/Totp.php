<?php
namespace App\Src;

/**
 * Time-based one-time passwords (RFC 6238: HMAC-SHA1, 30 s steps, 6
 * digits) — what Google Authenticator, Authy, 1Password etc. generate —
 * plus single-use backup codes for a lost phone.
 */
class Totp {
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP = 30;
    private const DIGITS = 6;

    /** New random secret, base32 (160 bits). */
    public static function generateSecret(): string {
        return self::base32Encode(random_bytes(20));
    }

    /** otpauth:// URI the authenticator app reads from the QR code. */
    public static function uri(string $secret, string $account, string $issuer = 'Jumplearner Admin'): string {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::STEP;
    }

    public static function code(string $secret, ?int $time = null): string {
        $counter = intdiv($time ?? time(), self::STEP);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Accepts the current code or one step either side (phone clock drift). */
    public static function verify(string $secret, string $code): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $now = time();
        foreach ([-1, 0, 1] as $drift) {
            if (hash_equals(self::code($secret, $now + $drift * self::STEP), $code)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0: string[], 1: string[]} [plaintext codes to show once, hashes to store] */
    public static function backupCodes(int $count = 8): array {
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtolower(self::base32Encode(random_bytes(5))); // 8 chars
            $plain[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return [$plain, array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $plain)];
    }

    /**
     * Checks $input against stored backup-code hashes. On a match returns
     * the remaining hashes (the used one removed); otherwise null.
     */
    public static function useBackupCode(array $hashes, string $input): ?array {
        $input = strtolower(trim($input));
        if (strlen(str_replace('-', '', $input)) === 8 && !str_contains($input, '-')) {
            $input = substr($input, 0, 4) . '-' . substr($input, 4);
        }
        foreach ($hashes as $i => $hash) {
            if (password_verify($input, $hash)) {
                unset($hashes[$i]);
                return array_values($hashes);
            }
        }
        return null;
    }

    private static function base32Encode(string $bytes): string {
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function base32Decode(string $b32): string {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($b32, '='))) as $c) {
            $pos = strpos(self::ALPHABET, $c);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
