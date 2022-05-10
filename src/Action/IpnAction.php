<?php

namespace App\Action;

use App\Paypal\IpnValidator;
use App\User\RegistrationService;
use App\User\SubscriptionService;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;

class IpnAction
{

    private IpnValidator $validator;
    private RegistrationService $registration;
    private SubscriptionService $subscription;

    public function __construct(IpnValidator $validator, SubscriptionService $subscription, RegistrationService $registration)
    {
        $this->validator = $validator;
        $this->registration = $registration;
        $this->subscription = $subscription;
    }

    public function handle(Request $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->validator->validate($request)) {
            $body = $request->getParsedBody();
            $email = $body["payer_email"];
            $externalSubscriptionId = $body["subscr_id"];
            $this->registration->register($email);
            $this->subscription->activate($email, $externalSubscriptionId, $body["item_number"]);
            if ($this->subscriptionPayment((array) $body)) {
                $this->subscription->addTime($externalSubscriptionId, $body["txn_id"]);
            }
        }
        return $response;
    }

    private function subscriptionPayment(array $body): bool
    {
        return $body["txn_type"] === "subscr_payment" && $body["payment_status"] === "Completed";
    }
}
