<?php

use App\Action\ActivateAction;
use App\Action\IpnAction;
use App\Paypal\IpnValidator;
use App\User\RegistrationService;
use App\User\SubscriptionService;
use GuzzleHttp\Client;
use Mezzio\Authentication\AuthenticationMiddleware;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepository\PdoDatabase;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

return function (ContainerInterface $container) {

    $container->set("authMiddleware", function () use ($container) {
        return new AuthenticationMiddleware($container->get("authentication"));
    });

    $container->set("IpnAction", function ($container) {
        return new IpnAction($container->get(IpnValidator::class), $container->get(SubscriptionService::class), $container->get(RegistrationService::class));
    });

    $container->set("ActivateAction", function ($con) {
        return new ActivateAction($con->get(RegistrationService::class));
    });

    $container->set(MailerInterface::class, function ($con) {
        $transport = (new EsmtpTransportFactory())->create(Dsn::fromString("smtp://07ea3bd5552b7a:302af4f41ffbaf@smtp.mailtrap.io:2525?encryption=tls&auth_mode=login"));
        return new Mailer($transport);
    });


    $container->set(RegistrationService::class, function ($c) {
        return new RegistrationService($c->get(MailerInterface::class), $c->get("pdo"));
    });
    $container->set(SubscriptionService::class, function ($c) {
        return new SubscriptionService($c->get("pdo"), $c->get(MailerInterface::class));
    });

    $container->set(IpnValidator::class, function ($container) {
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
        $pdo->query(
            "PRAGMA foreign_keys = ON;"
        );
        foreach ($container->get("migrations") as $changeSet) {
            $pdo->query($changeSet);
        }
        return $pdo;
    });
};
