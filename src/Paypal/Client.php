<?php

namespace App\Paypal;

use GuzzleHttp\Client as GuzzleHttpClient;

class Client
{

    private GuzzleHttpClient $client;
    private string $clientId;
    private string $clientSecret;
    private ?Token $token = null;

    public function __construct(GuzzleHttpClient $client, string $clientId, string $clientSecret)
    {
        $this->client = $client;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function cancelSubscription(string $id): void
    {
        if ($this->token === null || $this->token->expired()) {
            $this->token = $this->renew();
        }
        $this->client->post(
            "https://api.sandbox.paypal.com/v1/billing/subscriptions/$id/cancel",
            [
                "headers" => [
                    "Authorization" => "Bearer " . $this->token,
                    "Content-Type" => "application/json"
                ],
            ]
        );
    }

    private function renew(): Token
    {
        $time = time();
        $response = $this->client->post(
            "https://api.sandbox.paypal.com/v1/oauth2/token",
            [
                "auth" => [$this->clientId, $this->clientSecret],
                "body" => "grant_type=client_credentials"
            ]
        );
        $content = json_decode($response->getBody());
        return new Token($content->access_token, $time + $content->expires_in);
    }
}
