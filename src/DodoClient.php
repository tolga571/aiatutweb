<?php
namespace App\Src;

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
 *
 * Speaks raw cURL rather than Guzzle — see the note on request() below.
 */
class DodoClient
{
    private string $apiKey;
    private string $baseUri;

    public function __construct(string $apiKey, string $environment = 'live')
    {
        $this->apiKey = $apiKey;
        $this->baseUri = $environment === 'test' ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
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

    /**
     * Speaks raw HTTP via a stream context — deliberately not Guzzle, and
     * not PHP's curl extension either. A live billing-lifecycle audit
     * found that both crashed the entire worker (a bare Cloudflare 502,
     * not even our own catch-\Throwable running) specifically on
     * PATCH /subscriptions/{id} and POST /subscriptions/{id}/change-plan
     * against a subscription id Dodo rejected — reproducible on every
     * attempt, across Guzzle and native cURL, with and without forcing
     * HTTP/1.1. The one difference in Dodo's response on that path: a
     * `Connection: close` header that its 2xx responses don't send.
     * Something below PHP in this specific environment's curl/TLS stack
     * chokes on that combination hard enough to be uncatchable — the same
     * category of environment-specific curl-backed-client fragility as
     * the Guzzle 7.15.5 upgrade incident earlier on this project, just a
     * different trigger. Confirmed by direct comparison: the identical
     * PATCH over a stream-context file_get_contents() returned Dodo's 404
     * cleanly with no crash. Every failure mode here (a warning from
     * file_get_contents, a non-2xx status, unparseable JSON) is a plain
     * return value, nothing to mis-catch.
     */
    private function request(string $method, string $path, ?array $json = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $headers = "Authorization: Bearer {$this->apiKey}\r\nAccept: application/json\r\n";
        $content = null;
        if ($json !== null) {
            $headers .= "Content-Type: application/json\r\n";
            $content = json_encode(array_filter($json, fn($v) => $v !== null));
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $content,
                'timeout' => 15,
                'ignore_errors' => true, // still returns the body on 4xx/5xx instead of raising a warning-only failure
            ],
        ]);
        $body = @file_get_contents($this->baseUri . $path, false, $ctx);
        if ($body === false) {
            error_log("Dodo API {$method} {$path} request failed: " . (error_get_last()['message'] ?? 'unknown'));
            return null;
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int)$m[1];
            }
        }
        if ($status < 200 || $status >= 300) {
            error_log("Dodo API {$method} {$path} HTTP {$status}: " . substr($body, 0, 300));
            return null;
        }
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
