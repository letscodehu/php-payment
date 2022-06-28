<?php

use App\User\SubscriptionService;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;
use Slim\Psr7\Response as Psr7Response;

return function (App $app) {

    function view(Response $response, $template, $with = [])
    {
        extract($with);
        ob_start();
        require(__DIR__ . "/../templates/" . $template . ".phtml");
        $content = ob_get_clean();
        $response->getBody()->write($content);
        return $response;
    }

    $app->get('/subscription/cancel', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "cancel_confirmation", ["loggedIn" => $user]);
    });

    $app->post('/subscription/cancel', "SubscriptionCancelAction:handle");

    $app->get('/subscription/cancelled', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "subscription_cancelled", ["loggedIn" => $user]);
    });

    $app->post('/ipn', "IpnAction:handle");

    $app->get('/', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "index", ["loggedIn" => $user]);
    });

    $app->get('/logout', function (Request $request, Response $response, array $args) {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        $session->unset(UserInterface::class);
        $resp = new Psr7Response(302);
        return $resp->withHeader("Location", "/");
    });

    $app->get('/plans', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "plans", ["loggedIn" => $user, "buttons" => [
            "basic" => $_ENV["BASIC_BUTTON_ID"], "pro" => $_ENV["PRO_BUTTON_ID"], "enterprise" => $_ENV["ENTERPRISE_BUTTON_ID"]
        ]]);
    });

    $app->get('/profile', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        $subscriptionInfo = $this->get(SubscriptionService::class)->getSubscriptionInfo($user->getIdentity());
        return view(
            $response,
            "profile",
            [
                "subscribed" => $subscriptionInfo->isActive(),
                "email" => $user->getIdentity(),
                "loggedIn" => $user,
                "cancellable" => $subscriptionInfo->cancellable(),
                "status" => $subscriptionInfo->getStatus(),
                "expiry" => $subscriptionInfo->getExpires(),
                "transactions" => $subscriptionInfo->getTransactions(),
                "plan" => $subscriptionInfo->getPlan()
            ]

        );
    })->addMiddleware($app->getContainer()->get("authMiddleware"));

    $app->get('/success', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "success", ["loggedIn" => $user]);
    });

    $app->get('/login', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        if ($user) {
            $resp = new Psr7Response(302);
            return $resp->withHeader("Location", "/");
        }
        return view($response, "login");
    });

    $app->post('/login', function (Request $request, Response $response, array $args) {
        $auth = $this->get("authentication");
        if ($auth->authenticate($request)) {
            $resp = new Psr7Response(302);
            return $resp->withHeader("Location", "/");
        }
        return view($response, "login");
    });

    $app->get('/user/activate/{token}', function (Request $request, Response $response, array $args) {
        return view($response, "activate", ["token" => $args["token"]]);
    });

    $app->post('/user/activate', 'ActivateAction:activate');

    $app->get('/cancel', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "cancel", ["loggedIn" => $user]);
    });
    $app->get('/migrate', function (Request $request, Response $response) {
        $pdo = $this->get('pdo');
        foreach ($this->get("migrations") as $changeSet) {
            $pdo->query($changeSet);
        }
        return $response;
    });
};
