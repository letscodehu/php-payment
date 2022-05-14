<?php

namespace App\Action;

use App\User\RegistrationService;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ActivateAction
{

    private RegistrationService $register;

    public function __construct(RegistrationService $register)
    {
        $this->register = $register;
    }

    public function activate(Request $request, Response $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        if ($this->register->activate($body["token"], $body["password"], $body["password_confirm"])) {
            return $response->withStatus(302)->withHeader("Location", "/login");
        }
        return $response->withStatus(302)->withHeader("Location", "/user/activate/" . $body["token"]);
    }
}
