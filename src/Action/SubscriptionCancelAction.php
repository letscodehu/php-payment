<?php

namespace App\Action;

use App\User\SubscriptionService;
use Mezzio\Authentication\AuthenticationInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class SubscriptionCancelAction
{
    private SubscriptionService $subscriptionService;
    private AuthenticationInterface $authentication;

    public function __construct(SubscriptionService $subscriptionService, AuthenticationInterface $authenticationInterface)
    {
        $this->subscriptionService = $subscriptionService;
        $this->authentication = $authenticationInterface;
    }

    public function handle(Request $request, ResponseInterface $responseInterface): ResponseInterface
    {
        $user = $this->authentication->authenticate($request);
        $this->subscriptionService->cancelSubscription($user->getIdentity());
        return (new Response(302))->withAddedHeader("Location", "/subscription/cancelled");
    }
}
