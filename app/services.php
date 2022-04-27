<?php

use App\Action\IpnAction;
use App\Paypal\IpnValidator;
use GuzzleHttp\Client;
use Mezzio\Authentication\AuthenticationMiddleware;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepository\PdoDatabase;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ResponseFactory;

return function (ContainerInterface $container) {

    $container->set("authMiddleware", function () use ($container) {
        return new AuthenticationMiddleware($container->get("authentication"));
    });

    $container->set("IpnAction", function($container) {
        return new IpnAction($container->get(IpnValidator::class));
    });

    $container->set(IpnValidator::class, function($container) {
        return new IpnValidator(new Client());
    });

    $container->set("authentication", function () use ($container) {
        return new PhpSession(
            new PdoDatabase($container->get("pdo"), [
                "table" => "user",
                "field" => ["identity" => "username", "password" => "password"]
            ], $container->get("userFactory")),
            ["redirect" => "/login"],
            new ResponseFactory(),
            $container->get("userFactory")
        );
    });

    $container->set("userFactory", function () {
        return function ($identity, $roles, $details) {
            return new DefaultUser($identity, $roles, $details);
        };
    });

    $container->set("pdo", function ($container) {
        $pdo = new PDO("sqlite:db.sqlite");
        foreach ($container->get("migrations") as $changeSet) {
            $pdo->query($changeSet);
        }
        return $pdo;
    });
};
