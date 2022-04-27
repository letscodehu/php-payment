<?php

namespace App\Paypal;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;

class IpnValidator
{

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function validate(RequestInterface $request): bool
    {
        $requestBody = "cmd=_notify-validate&" . $request->getBody();
        $res = $this->client->request(
            "POST",
            "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr",
            ["body" => $requestBody]
        );
        return ($res->getBody() === "VERIFIED");
    }
}
