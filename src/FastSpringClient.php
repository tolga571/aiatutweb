<?php
namespace App\Src;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin wrapper around the FastSpring REST API (https://api.fastspring.com).
 *
 * Auth is HTTP Basic with the API username/password created under
 * Developer Tools > APIs > API Credentials in the FastSpring app. The same
 * credentials work for both test and live data — the storefront URL and the
 * `live` flag on each order/subscription are what separate the two.
 *
 * Used server-side only, for things the popup checkout can't do on its own:
 * verifying a completed order before granting access, and cancelling /
 * resuming / switching an existing subscription in place.
 */
class FastSpringClient
{
    private Client $http;
    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->http = new Client([
            'base_uri' => 'https://api.fastspring.com',
            'timeout' => 15.0,
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }

    /** GET /orders/{id} — returns the order object, or null on failure. */
    public function getOrder(string $orderId): ?array
    {
        $data = $this->request('GET', '/orders/' . rawurlencode($orderId));
        if ($data === null) {
            return null;
        }
        // Multi-id lookups come back wrapped in an array; single lookups
        // are the order object itself. Accept either.
        if (isset($data['orders'][0]) && is_array($data['orders'][0])) {
            return $data['orders'][0];
        }
        return isset($data['id']) || isset($data['order']) ? $data : null;
    }

    /** GET /subscriptions/{id} — returns the subscription object, or null. */
    public function getSubscription(string $subscriptionId): ?array
    {
        $data = $this->request('GET', '/subscriptions/' . rawurlencode($subscriptionId));
        if ($data === null) {
            return null;
        }
        if (isset($data['subscriptions'][0]) && is_array($data['subscriptions'][0])) {
            return $data['subscriptions'][0];
        }
        return isset($data['id']) || isset($data['subscription']) ? $data : null;
    }

    /**
     * GET /subscriptions?accountId=... — every subscription on an account,
     * as full objects. Used to find the subscription a just-completed order
     * created, since the order object only carries the account ID.
     */
    public function getSubscriptionsForAccount(string $accountId): array
    {
        $data = $this->request('GET', '/subscriptions', [
            'accountId' => $accountId,
            'scope' => 'all',
            'limit' => 50,
        ]);
        $list = $data['subscriptions'] ?? [];
        $result = [];
        foreach ($list as $item) {
            if (is_array($item)) {
                $result[] = $item;
            } elseif (is_string($item) && $item !== '') {
                // Some response shapes only return IDs — resolve each one.
                $sub = $this->getSubscription($item);
                if ($sub) {
                    $result[] = $sub;
                }
            }
        }
        return $result;
    }

    /**
     * DELETE /subscriptions/{id} — cancels at the end of the current billing
     * period (billingPeriod=1), so the customer keeps what they paid for.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        return $this->request('DELETE', '/subscriptions/' . rawurlencode($subscriptionId), ['billingPeriod' => 1]) !== null;
    }

    /** Removes a scheduled cancellation so the subscription keeps renewing. */
    public function uncancelSubscription(string $subscriptionId): bool
    {
        return $this->updateSubscriptions([
            ['subscription' => $subscriptionId, 'deactivation' => null],
        ]);
    }

    /**
     * Switches a subscription to another product in place.
     * $prorate = true charges/credits the difference now (used for upgrades);
     * false leaves the current period alone and bills the new price from the
     * next rebill (used for downgrades).
     */
    public function changeSubscriptionProduct(string $subscriptionId, string $productPath, bool $prorate): bool
    {
        return $this->updateSubscriptions([
            [
                'subscription' => $subscriptionId,
                'product' => $productPath,
                'quantity' => 1,
                'prorate' => $prorate,
            ],
        ]);
    }

    private function updateSubscriptions(array $subscriptions): bool
    {
        $data = $this->request('POST', '/subscriptions', [], ['subscriptions' => $subscriptions]);
        if ($data === null) {
            return false;
        }
        // POST /subscriptions returns a per-item result; treat any item
        // reporting an error as a failure instead of silently succeeding.
        foreach ($data['subscriptions'] ?? [] as $item) {
            if (is_array($item) && (($item['result'] ?? 'success') === 'error' || !empty($item['error']))) {
                error_log('FastSpring updateSubscriptions item error: ' . json_encode($item));
                return false;
            }
        }
        return true;
    }

    private function request(string $method, string $path, array $query = [], ?array $json = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $options = [
            'auth' => [$this->username, $this->password],
            'headers' => ['Accept' => 'application/json'],
        ];
        if ($query) {
            $options['query'] = $query;
        }
        if ($json !== null) {
            $options['json'] = $json;
        }
        try {
            $response = $this->http->request($method, $path, $options);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return null;
            }
            $body = (string)$response->getBody();
            if ($body === '') {
                return [];
            }
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        } catch (GuzzleException $e) {
            error_log("FastSpring API {$method} {$path} error: " . $e->getMessage());
            return null;
        }
    }
}
