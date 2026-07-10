<?php
namespace App\Src;

use GuzzleHttp\Client;

class Mailer
{
    private Client $http;
    private string $apiToken;
    private string $fromAddress;
    private string $fromName;

    public function __construct(array $config)
    {
        $this->apiToken = $config['mailtrap_api_token'] ?? '';
        $this->fromAddress = $config['mail_from_address'] ?? 'noreply@jumplearner.com';
        $this->fromName = $config['mail_from_name'] ?? 'Jumplearner';
        $this->http = new Client([
            'base_uri' => 'https://send.api.mailtrap.io/',
            'timeout' => 10.0,
        ]);
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (empty($this->apiToken)) {
            return false;
        }

        try {
            $response = $this->http->post('api/send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'from' => [
                        'email' => $this->fromAddress,
                        'name' => $this->fromName,
                    ],
                    'to' => [
                        ['email' => $to],
                    ],
                    'subject' => $subject,
                    'text' => $textBody ?? strip_tags($htmlBody),
                    'html' => $htmlBody,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("Mailtrap send error: " . $e->getMessage());
            return false;
        }
    }
}
