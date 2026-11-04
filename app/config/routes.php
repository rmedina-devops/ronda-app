<?php

use App\Controller\UserController;

$router->get('/', function () {
    echo Flight::view()->run('welcome', [
        'title' => 'Ronda App',
        'heading' => '¡Bienvenido a Ronda App!'
    ]);
});

$router->get('/login', [UserController::class, 'login']);