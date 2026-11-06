<?php

namespace App\Controller;
use flight\Engine;

class AuthController {

    protected Engine $app;
    public function __construct(Engine $app) {
        $this->app = $app;
    }

    public function login() {
        echo $this->app->view()->run('login', [
            'title' => 'Login',
            'heading' => 'Iniciar sesión'
        ]);
    }
}