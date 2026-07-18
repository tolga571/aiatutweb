<?php
/**
 * Paddle Billing CLI utility.
 *
 * Uses the same server-side REST API PaddleClient.php already talks to
 * (the official paddlehq/paddle-php-sdk requires PHP ^8.1; this box runs
 * PHP 8.0, so this goes straight to the REST API via Guzzle instead).
 *
 * Targets whichever account PADDLE_API_KEY + PADDLE_ENVIRONMENT in .env
 * point to (sandbox unless PADDLE_ENVIRONMENT=production).
 *
 * Usage:
 *   php scripts/paddle_manage.php list-plans
 *   php scripts/paddle_manage.php create-plan --name="Team Plan" --price=2999 --currency=USD --interval=month [--frequency=1] [--description="..."] [--tax-category=standard]
 *   php scripts/paddle_manage.php list-subscribers [--status=active]
 *
 * --price is in the smallest currency unit (e.g. cents for USD): 2999 = $29.99.
 */

require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$apiKey = $config['paddle_api_key'] ?? '';
$environment = $config['paddle_environment'] ?? 'sandbox';

if ($apiKey === '') {
    fwrite(STDERR, "Error: PADDLE_API_KEY is not set in .env.\n");
    fwrite(STDERR, "Add your " . ($environment === 'production' ? 'LIVE' : 'sandbox') . " API key (Paddle Dashboard > Developer Tools > Authentication) as PADDLE_API_KEY and re-run.\n");
    exit(1);
}

$baseUrl = $environment === 'production' ? 'https://api.paddle.com' : 'https://sandbox-api.paddle.com';
$http = new Client([
    'base_uri' => $baseUrl,
    'timeout' => 15.0,
    'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
    ],
]);

$command = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9_-]+)=(.*)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    }
}

fwrite(STDOUT, "Paddle environment: {$environment} ({$baseUrl})\n\n");

switch ($command) {
    case 'list-plans':
        listPlans($http, $options);
        break;
    case 'create-plan':
        createPlan($http, $options);
        break;
    case 'list-subscribers':
        listActiveSubscribers($http, $options);
        break;
    default:
        fwrite(STDERR, "Usage:\n");
        fwrite(STDERR, "  php scripts/paddle_manage.php list-plans\n");
        fwrite(STDERR, "  php scripts/paddle_manage.php create-plan --name=\"Team Plan\" --price=2999 --currency=USD --interval=month [--frequency=1] [--description=\"...\"] [--tax-category=standard]\n");
        fwrite(STDERR, "  php scripts/paddle_manage.php list-subscribers [--status=active]\n");
        exit(1);
}

function listPlans(Client $http, array $options): void
{
    $status = $options['status'] ?? 'active';

    try {
        $products = [];
        $after = null;
        do {
            $query = ['per_page' => 100];
            if ($status !== 'all') {
                $query['status'] = $status;
            }
            if ($after) {
                $query['after'] = $after;
            }
            $resp = $http->get('/products', ['query' => $query]);
            $decoded = json_decode((string)$resp->getBody(), true);
            foreach ($decoded['data'] ?? [] as $product) {
                $products[$product['id']] = $product;
            }
            $pagination = $decoded['meta']['pagination'] ?? [];
            $after = null;
            if (!empty($pagination['has_more']) && !empty($pagination['next'])) {
                $parts = parse_url($pagination['next']);
                parse_str($parts['query'] ?? '', $qs);
                $after = $qs['after'] ?? null;
            }
        } while ($after);

        $prices = [];
        $after = null;
        do {
            $query = ['per_page' => 100];
            if ($status !== 'all') {
                $query['status'] = $status;
            }
            if ($after) {
                $query['after'] = $after;
            }
            $resp = $http->get('/prices', ['query' => $query]);
            $decoded = json_decode((string)$resp->getBody(), true);
            foreach ($decoded['data'] ?? [] as $price) {
                $prices[$price['product_id']][] = $price;
            }
            $pagination = $decoded['meta']['pagination'] ?? [];
            $after = null;
            if (!empty($pagination['has_more']) && !empty($pagination['next'])) {
                $parts = parse_url($pagination['next']);
                parse_str($parts['query'] ?? '', $qs);
                $after = $qs['after'] ?? null;
            }
        } while ($after);

        echo "Products (status={$status}): " . count($products) . "\n\n";
        foreach ($products as $productId => $product) {
            echo "- {$product['name']}  [{$productId}]  status={$product['status']}\n";
            foreach ($prices[$productId] ?? [] as $price) {
                $amount = $price['unit_price']['amount'] ?? '?';
                $currency = $price['unit_price']['currency_code'] ?? '?';
                $interval = $price['billing_cycle']['interval'] ?? 'one-time';
                $frequency = $price['billing_cycle']['frequency'] ?? 1;
                echo "    price {$price['id']}  {$amount} {$currency} / every {$frequency} {$interval}  status={$price['status']}\n";
            }
            if (empty($prices[$productId])) {
                echo "    (no prices)\n";
            }
        }

        $orphanPriceIds = array_diff(array_keys($prices), array_keys($products));
        if (!empty($orphanPriceIds)) {
            echo "\nPrices whose product wasn't in this status filter:\n";
            foreach ($orphanPriceIds as $pid) {
                echo "  product {$pid}: " . count($prices[$pid]) . " price(s)\n";
            }
        }
    } catch (RequestException $e) {
        $body = $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
        fwrite(STDERR, "Paddle API error: {$body}\n");
        exit(1);
    }
}

function createPlan(Client $http, array $options): void
{
    $productId = $options['product-id'] ?? null; // attach to an existing product instead of creating a new one
    $name = $options['name'] ?? null;
    $priceAmount = $options['price'] ?? null; // smallest currency unit, e.g. cents
    $currency = strtoupper($options['currency'] ?? 'USD');
    $interval = $options['interval'] ?? 'month'; // day|week|month|year
    $frequency = (int)($options['frequency'] ?? 1);
    $description = $options['description'] ?? $name;
    $taxCategory = $options['tax-category'] ?? 'standard';

    if (!$priceAmount || (!$productId && !$name)) {
        fwrite(STDERR, "Error: --price is required, plus either --product-id (attach to an existing product) or --name (create a new product).\n");
        fwrite(STDERR, "Example: --product-id=pro_xxx --price=50000 --currency=USD --interval=year --description=\"Pro - Yearly\"\n");
        exit(1);
    }
    if (!ctype_digit((string)$priceAmount)) {
        fwrite(STDERR, "Error: --price must be an integer in the smallest currency unit (e.g. 2999 for \$29.99).\n");
        exit(1);
    }

    try {
        if ($productId) {
            echo "Using existing product: {$productId}\n";
        } else {
            $productResp = $http->post('/products', [
                'json' => [
                    'name' => $name,
                    'description' => $description,
                    'tax_category' => $taxCategory,
                ],
            ]);
            $product = json_decode((string)$productResp->getBody(), true)['data'];
            $productId = $product['id'];
            echo "Created product: {$product['id']} ({$product['name']})\n";
        }

        $priceResp = $http->post('/prices', [
            'json' => [
                'product_id' => $productId,
                'description' => ($description ?? $name) . ' - recurring price',
                'unit_price' => [
                    'amount' => (string)$priceAmount,
                    'currency_code' => $currency,
                ],
                'billing_cycle' => [
                    'interval' => $interval,
                    'frequency' => $frequency,
                ],
            ],
        ]);
        $price = json_decode((string)$priceResp->getBody(), true)['data'];
        echo "Created price: {$price['id']} ({$priceAmount} {$currency} / every {$frequency} {$interval})\n";

        echo "\nDone. To use it in the app, point one of the PADDLE_*_PRICE_ID vars at:\n";
        echo "  {$price['id']}\n";
    } catch (RequestException $e) {
        $body = $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
        fwrite(STDERR, "Paddle API error: {$body}\n");
        exit(1);
    }
}

function listActiveSubscribers(Client $http, array $options): void
{
    $status = $options['status'] ?? 'active';
    $after = null;
    $rows = [];

    try {
        do {
            $query = ['per_page' => 50];
            if ($status !== 'all') {
                $query['status'] = $status;
            }
            if ($after) {
                $query['after'] = $after;
            }
            $resp = $http->get('/subscriptions', ['query' => $query]);
            $decoded = json_decode((string)$resp->getBody(), true);
            foreach ($decoded['data'] ?? [] as $sub) {
                $rows[] = [
                    'id' => $sub['id'],
                    'status' => $sub['status'],
                    'customer_id' => $sub['customer_id'],
                    'price_id' => $sub['items'][0]['price']['id'] ?? '',
                    'next_billed_at' => $sub['next_billed_at'] ?? '',
                ];
            }
            $pagination = $decoded['meta']['pagination'] ?? [];
            $after = null;
            if (!empty($pagination['has_more']) && !empty($pagination['next'])) {
                $parts = parse_url($pagination['next']);
                parse_str($parts['query'] ?? '', $qs);
                $after = $qs['after'] ?? null;
            }
        } while ($after);

        echo "Subscriptions with status='{$status}': " . count($rows) . "\n\n";
        printf("%-28s %-10s %-28s %-28s %-20s\n", 'Subscription ID', 'Status', 'Customer ID', 'Price ID', 'Next Billed At');
        foreach ($rows as $row) {
            printf("%-28s %-10s %-28s %-28s %-20s\n", $row['id'], $row['status'], $row['customer_id'], $row['price_id'], $row['next_billed_at']);
        }
    } catch (RequestException $e) {
        $body = $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
        fwrite(STDERR, "Paddle API error: {$body}\n");
        exit(1);
    }
}
