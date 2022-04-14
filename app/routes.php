<?php

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
        return view($response, "plans", ["loggedIn" => $user]);
    });

    $app->post('/ipn', function (Request $request, Response $response, array $args) {
        $requestBody = "cmd=_notify-validate&" . $request->getBody();
        $client = new \GuzzleHttp\Client();
        $res = $client->request(
            "POST",
            "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr",
            ["body" => $requestBody]
        );
        if ($res->getBody() === "VERIFIED") {
            // check if the ipn_track_id already processed
            // check on whats in the body
            // register the user with the email
            // activate the subscription
            // send email with activation info
            // store the ipn_track_ids which were processed
        }
        return $response;
    });
    $app->get('/profile', function (Request $request, Response $response, array $args) {
        $user = $this->get('authentication')->authenticate($request);
        return view($response, "index", ["loggedIn" => $user]);
    })->addMiddleware($app->getContainer()->get("authMiddleware"));

    $app->get('/success', function (Request $request, Response $response, array $args) {
        return view($response, "success");
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

    $app->get('/cancel', function (Request $request, Response $response, array $args) {
        return view($response, "cancel");
    })->addMiddleware($app->getContainer()->get('authMiddleware'));
};
