<?php

use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app) {
    $app->addMiddleware(new AuthMiddleware);
    $app->addErrorMiddleware(true, true, true);
};
