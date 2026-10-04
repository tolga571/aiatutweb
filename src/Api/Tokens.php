<?php
namespace App\Src\Api;

use App\Src\Database;

/**
 * Bearer tokens for the mobile app. The plaintext token is shown to the app
 * once at sign-in; only its SHA-256 is stored. Each use slides the expiry
 * forward (like the web's 90-day session), so an app that's opened at least
 * every 90 days stays signed in.
 */
class Tokens
{
    public const TTL_DAYS = 90;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function issue(int $userId, string $device = '', string $platform = '', string $appVersion = ''): string
    {
        $token = 'jl_' . bin2hex(random_bytes(32));
        $this->db->execute(
            "INSERT INTO api_tokens (user_id, token_hash, device, platform, app_version, expires_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP + INTERVAL '" . self::TTL_DAYS . " days')",
            [$userId, hash('sha256', $token), mb_substr($device, 0, 100), mb_substr($platform, 0, 20), mb_substr($appVersion, 0, 20)]
        );
        // Keep the table tidy: expired tokens are useless.
        $this->db->execute('DELETE FROM api_tokens WHERE expires_at < CURRENT_TIMESTAMP');
        return $token;
    }

    /**
     * The user for a valid token, or null (unknown, expired, or the account
     * was suspended). Refreshes last-use / expiry at most once an hour.
     */
    public function user(string $token): ?array
    {
        if (!str_starts_with($token, 'jl_')) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT t.id AS token_id, t.last_used_at, u.* FROM api_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > CURRENT_TIMESTAMP',
            [hash('sha256', $token)]
        );
        if (!$row || !empty($row['suspended_at'])) {
            return null;
        }
        if (strtotime((string)$row['last_used_at']) < time() - 3600) {
            $this->db->execute(
                "UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP, expires_at = CURRENT_TIMESTAMP + INTERVAL '" . self::TTL_DAYS . " days' WHERE id = ?",
                [$row['token_id']]
            );
        }
        unset($row['token_id'], $row['last_used_at']);
        return $row;
    }

    public function revoke(string $token): void
    {
        $this->db->execute('DELETE FROM api_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    public function revokeAll(int $userId): void
    {
        $this->db->execute('DELETE FROM api_tokens WHERE user_id = ?', [$userId]);
    }
}
