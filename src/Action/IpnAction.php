<?php

namespace App\Action;

use App\Paypal\IpnValidator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class IpnAction {

    private IpnValidator $validator;

    public function __construct(IpnValidator $validator)
    {
        $this->validator = $validator;
    }

    public function handle(RequestInterface $request, ResponseInterface $response) : ResponseInterface {
        if ($this->validator->validate($request)) {
            // check on whats in the body
            // register the user with the email
            // - create user record
            // - create activation token
            // - send email with token
            // activate the subscription
            // - create subscription record 
            // - if payment was made create subscription timeframe
        }
        return $response;
    }

}

