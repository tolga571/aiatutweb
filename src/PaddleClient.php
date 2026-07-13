<?php
namespace App\Src;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin wrapper around the server-side Paddle Billing REST API.
 * Used for actions the client-side Paddle.js checkout widget cannot do:
 * cancelling an existing subscription or swapping its price without
 * creating a second, duplicate subscription.
 */
class PaddleClient
{
    private Client $http;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $environment = 'sandbox')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';
        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15.0,
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Cancels a subscription. By default the cancellation takes effect at
     * the end of the current billing period, so the customer keeps access
     * they already paid for instead of losing it immediately.
     */
    public function cancelSubscription(string $subscriptionId, string $effectiveFrom = 'next_billing_period'): bool
    {
        try {
            $response = $this->http->post("/subscriptions/{$subscriptionId}/cancel", [
                'headers' => $this->headers(),
                'json' => ['effective_from' => $effectiveFrom],
            ]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log('Paddle cancelSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Removes a pending scheduled change (e.g. an end-of-period
     * cancellation) so the subscription keeps renewing normally. Lets a
     * user who cancelled and changed their mind get back to an active
     * subscription without support having to do it by hand.
     */
    public function resumeSubscription(string $subscriptionId): bool
    {
        try {
            $response = $this->http->patch("/subscriptions/{$subscriptionId}", [
                'headers' => $this->headers(),
                'json' => ['scheduled_change' => null],
            ]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log('Paddle resumeSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Swaps a subscription to a different price (upgrade/downgrade) in
     * place, so the customer ends up with a single subscription instead of
     * an extra one stacked on top of the old plan.
     *
     * Follows standard subscription-billing convention: upgrades apply
     * immediately with a prorated charge for the difference (the customer
     * gets the higher tier right away), while downgrades are deferred to
     * the next billing period (the customer keeps what they already paid
     * for until then, and simply pays less starting next cycle) rather
     * than issuing an immediate prorated credit.
     */
    public function updateSubscriptionPrice(string $subscriptionId, string $priceId, bool $isUpgrade): bool
    {
        try {
            $response = $this->http->patch("/subscriptions/{$subscriptionId}", [
                'headers' => $this->headers(),
                'json' => [
                    'items' => [
                        ['price_id' => $priceId, 'quantity' => 1],
                    ],
                    'proration_billing_mode' => $isUpgrade ? 'prorated_immediately' : 'prorated_next_billing_period',
                ],
            ]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log('Paddle updateSubscriptionPrice error: ' . $e->getMessage());
            return false;
        }
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
