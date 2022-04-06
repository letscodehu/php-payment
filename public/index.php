<?php

use DI\Container;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$services = require __DIR__."/../app/services.php";
$services($container);

AppFactory::setContainer($container);
$app = AppFactory::create();

$middleware = require __DIR__."/../app/middlewares.php";
$middleware($app);

$routes = require __DIR__."/../app/routes.php";
$routes($app);

$app->run();
