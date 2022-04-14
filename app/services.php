<?php

use Mezzio\Authentication\AuthenticationMiddleware;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\DefaultUserFactory;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepository\PdoDatabase;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ResponseFactory;

return function (ContainerInterface $container) {
    $container->set("authMiddleware", function () use ($container) {
        return new AuthenticationMiddleware($container->get("authentication"));
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

    $container->set("pdo", function () {
        $pdo = new PDO("sqlite:db.sqlite");
        $pdo->query("DROP TABLE IF EXISTS user");
        $pdo->query("CREATE TABLE user (id INT(11) PRIMARY KEY, username varchar(32), password varchar(32))");
        $pass = password_hash("training", PASSWORD_BCRYPT);
        $pdo->query("INSERT INTO user (id, username, password) VALUES (1, 'training', '$pass')");
        return $pdo;
    });
};
