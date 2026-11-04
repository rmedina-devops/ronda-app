<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = Flight::app();

require_once __DIR__ . '/../app/config/services.php';
$router = $app->router();
require_once __DIR__ . '/../app/config/routes.php';

Flight::start();