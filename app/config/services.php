<?php

use eftec\bladeone\BladeOne;
use Dice\Dice;
use flight\Engine;

$app = Flight::app();

// Directorio de vistas y caché
$viewsPath = __DIR__ . '/../Views';
$cachePath = __DIR__ . '/../storage/cache/views';

$app->register('view', BladeOne::class, [$viewsPath, $cachePath, BladeOne::MODE_AUTO]);
$app->map('render', function ($template, $data = []) use ($app) {
    return $app->view->run($template, $data);
});
// Load configuration
$container = new Dice();
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