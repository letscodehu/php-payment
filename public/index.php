<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write(file_get_contents("templates/index.html"));
    return $response;
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
    $response->getBody()->write(file_get_contents("templates/success.html"));
    return $response;
});
$app->get('/cancel', function (Request $request, Response $response, array $args) {
    $response->getBody()->write(file_get_contents("templates/cancel.html"));
    return $response;
});
$app->run();
