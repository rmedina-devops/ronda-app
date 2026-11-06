<?php

use eftec\bladeone\BladeOne;
use flight\Engine;


$app = Flight::app();

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__,2));
$dotenv->load();

// Directorio de vistas y caché
$viewsPath = __DIR__ . '/../Views';
$cachePath = __DIR__ . '/../../storage/cache/views';

$mode  = getenv('APP_ENV') === 'production' ? BladeOne::MODE_FAST : BladeOne::MODE_AUTO;
$app->register('view', BladeOne::class, [$viewsPath, $cachePath, $mode]);
$app->map('render', function ($template, $data = []) use ($app) {
    return $app->view->run($template, $data);
});
// Load configuration
$container = require __DIR__ . '/container.php';
$container = $container->addRule('*', [
    'substitutions' => [
        Engine::class => $app,
    ]
]);

$app->registerContainerHandler(function ($class, $params) use ($container) {
    return $container->create($class, $params);
});

$app->map('make', function ($class, $params = []) use ($container) {
    return $container->create($class, $params);
});