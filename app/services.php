<?php

use App\Action\ActivateAction;
use App\Action\IpnAction;
use App\Action\SubscriptionCancelAction;
use App\Paypal\Client as PaypalClient;
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

    $container->set("SubscriptionCancelAction", function ($c) {
        return new SubscriptionCancelAction($c->get(SubscriptionService::class), $c->get("authentication"));
    });

    $container->set(PaypalClient::class, function ($c) {
        return new PaypalClient(new Client(), $_ENV["PAYPAL_CLIENT_ID"], $_ENV["PAYPAL_CLIENT_SECRET"]);
    });
    $container->set("ActivateAction", function ($con) {
        return new ActivateAction($con->get(RegistrationService::class));
    });

    $container->set("SubscriptionCancelAction", function ($con) {
        return new SubscriptionCancelAction($con->get(SubscriptionService::class), $con->get('authentication'));
    });

    $container->set(MailerInterface::class, function ($con) {
        $transport = (new EsmtpTransportFactory())->create(Dsn::fromString($_ENV["MAILER_DSN"]));
        return new Mailer($transport);
    });


    $container->set(RegistrationService::class, function ($c) {
        return new RegistrationService($c->get(MailerInterface::class), $c->get("pdo"));
    });
    $container->set(SubscriptionService::class, function ($c) {
        return new SubscriptionService($c->get("pdo"), $c->get(MailerInterface::class), $c->get(PaypalClient::class));
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
        $pdo = new PDO($_ENV["DB_DSN"], $_ENV["DB_USER"], $_ENV["DB_PASSWORD"]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!$_ENV["DB_USER"]) {
            $pdo->query(
                "PRAGMA foreign_keys = ON;"
            );
        }
        return $pdo;
    });
};
