<?php
namespace App\Src;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Checks incoming webhook requests against Paddle's published live IP
 * ranges (https://api.paddle.com/ips). The list is cached to disk and
 * refreshed periodically rather than hardcoded, since Paddle can change it.
 */
class PaddleIpAllowlist
{
    private const SOURCE_URL = 'https://api.paddle.com/ips';
    private const CACHE_TTL_SECONDS = 21600; // 6 hours

    public static function isAllowed(string $ip, string $cacheFile): bool
    {
        $cidrs = self::getCidrs($cacheFile);
        if ($cidrs === null) {
            // Couldn't fetch and no cache exists yet — don't let an infra
            // hiccup block every webhook; signature verification is still
            // enforced regardless of this check.
            return true;
        }
        foreach ($cidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private static function getCidrs(string $cacheFile): ?array
    {
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL_SECONDS) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $fresh = self::fetchCidrs();
        if ($fresh !== null) {
            file_put_contents($cacheFile, json_encode($fresh));
            return $fresh;
        }

        // Fetch failed — fall back to a stale cache rather than failing open/closed blindly.
        if (is_file($cacheFile)) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        return null;
    }

    private static function fetchCidrs(): ?array
    {
        try {
            $http = new Client(['timeout' => 5.0]);
            $response = $http->get(self::SOURCE_URL);
            $body = json_decode((string)$response->getBody(), true);
            $cidrs = $body['data']['ipv4_cidrs'] ?? null;
            return is_array($cidrs) && !empty($cidrs) ? $cidrs : null;
        } catch (GuzzleException $e) {
            error_log('PaddleIpAllowlist fetch error: ' . $e->getMessage());
            return null;
        }
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $maskBits = (int)$maskBits;
        if ($maskBits <= 0) {
            return true;
        }
        $mask = $maskBits >= 32 ? -1 : (-1 << (32 - $maskBits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
