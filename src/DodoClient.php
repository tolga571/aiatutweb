<?php
namespace App\Src;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin wrapper around the Dodo Payments REST API
 * (https://live.dodopayments.com / https://test.dodopayments.com).
 *
 * Auth is a Bearer API key from Developer > API Keys in the Dodo dashboard.
 * Live and test mode use separate base URLs and separate keys/catalogs
 * (unlike FastSpring, where the same key serves both and a `live` flag on
 * the object tells them apart).
 *
 * Used server-side only: creating a hosted checkout session for a plan, and
 * cancelling / resuming / switching an existing subscription in place.
 */
class DodoClient
{
    private Client $http;
    private string $apiKey;

    public function __construct(string $apiKey, string $environment = 'live')
    {
        $this->apiKey = $apiKey;
        $baseUri = $environment === 'test' ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
        $this->http = new Client([
            'base_uri' => $baseUri,
            'timeout' => 15.0,
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * POST /checkouts — hosted checkout session for a single subscription
     * product. Returns the URL to redirect the customer to, or null on
     * failure. Dodo's own page collects billing address/payment method;
     * nothing about billing needs to be gathered in-app first.
     */
    public function createCheckoutSession(
        string $productId,
        string $email,
        int $userId,
        string $returnUrl,
        ?string $cancelUrl = null
    ): ?string {
        $data = $this->request('POST', '/checkouts', [
            'product_cart' => [['product_id' => $productId, 'quantity' => 1]],
            'customer' => ['email' => $email],
            'metadata' => ['user_id' => (string)$userId],
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            // Catalog prices are USD-only, but this lets a customer switch
            // the checkout to their own currency (Dodo handles the
            // conversion) instead of being stuck paying in USD regardless
            // of where they are.
            'feature_flags' => ['allow_currency_selection' => true],
        ]);
        return $data['checkout_url'] ?? null;
    }

    /** GET /subscriptions/{id} — the subscription object, or null. */
    public function getSubscription(string $subscriptionId): ?array
    {
        return $this->request('GET', '/subscriptions/' . rawurlencode($subscriptionId));
    }

    /**
     * PATCH /subscriptions/{id} — cancel at the end of the current billing
     * period so the customer keeps what they paid for.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        return $this->request('PATCH', '/subscriptions/' . rawurlencode($subscriptionId), [
            'cancel_at_next_billing_date' => true,
        ]) !== null;
    }

    /** Removes a scheduled cancellation so the subscription keeps renewing. */
    public function resumeSubscription(string $subscriptionId): bool
    {
        return $this->request('PATCH', '/subscriptions/' . rawurlencode($subscriptionId), [
            'cancel_at_next_billing_date' => false,
        ]) !== null;
    }

    /**
     * POST /customers/{id}/customer-portal/session — a hosted page where
     * the customer can view their invoices/receipts and payment methods
     * without us building any of that ourselves. Returns the portal URL,
     * or null on failure.
     */
    public function createCustomerPortalSession(string $customerId, string $returnUrl): ?string
    {
        $data = $this->request('POST', '/customers/' . rawurlencode($customerId) . '/customer-portal/session', [
            'return_url' => $returnUrl,
        ]);
        return $data['link'] ?? null;
    }

    /**
     * POST /subscriptions/{id}/change-plan — switches to another product.
     * $prorate = true charges/credits the difference now (upgrades);
     * false defers to the next billing date (downgrades).
     */
    public function changeSubscriptionPlan(string $subscriptionId, string $productId, bool $prorate): bool
    {
        return $this->request('POST', '/subscriptions/' . rawurlencode($subscriptionId) . '/change-plan', [
            'product_id' => $productId,
            'quantity' => 1,
            'proration_billing_mode' => $prorate ? 'prorated_immediately' : 'do_not_bill',
            'effective_at' => $prorate ? 'immediately' : 'next_billing_date',
        ]) !== null;
    }

    private function request(string $method, string $path, ?array $json = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = array_filter($json, fn($v) => $v !== null);
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
            error_log("Dodo API {$method} {$path} error: " . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            // Defensive: cancel/resume/change-plan were crashing the whole
            // request (Cloudflare 502, not our own JSON error response) on
            // a non-existent subscription id, even though the equivalent
            // Guzzle call for checkouts/customer-portal degrades cleanly.
            // Whatever this actually is, it isn't a GuzzleException, so it
            // was never being caught above — catch broadly here so a
            // provider-side failure degrades to "action failed", not a
            // crashed worker.
            error_log("Dodo API {$method} {$path} unexpected error: " . get_class($e) . ': ' . $e->getMessage());
            return null;
        }
    }
}
