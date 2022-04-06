<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

return function (App $app) {

    function view(Response $response, $template, $with = []) {
        extract($with);
        ob_start();
        require(__DIR__."/../templates/". $template.".phtml");
        $content = ob_get_clean();
        $response->getBody()->write($content);
        return $response;
    }

    $app->get('/', function (Request $request, Response $response, array $args) {
        return view($response, "index");
    });

    $app->post('/ipn', function (Request $request, Response $response, array $args) {
        $requestBody = "cmd=_notify-validate&" . $request->getBody();
        $client = new \GuzzleHttp\Client();
        $res = $client->request(
            "POST",
            "https://ipnpb.sandbox.paypal.com/cgi-bin/webscr",
            ["body" => $requestBody]
        );
        if ($res->getBody() == "VERIFIED") {
            file_put_contents("requestbody", "valid");
            // valid hívás
        } else {
            file_put_contents("requestbody", "invalid");
            // invalid hívás
        }
        return $response;
    });
    $app->get('/success', function (Request $request, Response $response, array $args) {
        return view($response, "success");
    });
    $app->get('/cancel', function (Request $request, Response $response, array $args) {
        return view($response, "cancel");
    });
};
