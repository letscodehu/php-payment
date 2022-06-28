<?php

use DI\Container;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;


require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__.'/..');
$dotenv->safeLoad();
$dotenv->required(["DB_DSN", "PAYPAL_CLIENT_ID", "PAYPAL_CLIENT_SECRET", "MAILER_DSN", "BASIC_BUTTON_ID", "PRO_BUTTON_ID", "ENTERPRISE_BUTTON_ID"]);

$container = new Container();

$migrations = require __DIR__."/../app/migrations.php";
$container->set("migrations", $migrations);

$services = require __DIR__."/../app/services.php";
$services($container);

AppFactory::setContainer($container);
$app = AppFactory::create();

$middleware = require __DIR__."/../app/middlewares.php";
$middleware($app);

$routes = require __DIR__."/../app/routes.php";
$routes($app);

$app->run();
