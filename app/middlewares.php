<?php

use Mezzio\Session\Ext\PhpSessionPersistence;
use Mezzio\Session\SessionMiddleware;
use Slim\App;

return function (App $app) {
    $app->addMiddleware(new SessionMiddleware(new PhpSessionPersistence()));
    $app->addErrorMiddleware(true, true, true);
};
