<?php

return [
        'app' => [
            'env' => getenv('APP_ENV') ?: 'production',
        ],
        'database' => [
            'connection'    => getenv('DB_CONNECTION') ?: 'sqlite',
            'path'          => getenv('DB_PATH') ?: '',
            'username'      => getenv('DB_USERNAME') ?: '',
            'password'      => getenv('DB_PASSWORD') ?: '',
            'host'          => getenv('DB_HOST') ?: '',
            'port'          => getenv('DB_PORT') ?: '',
            'name'          => getenv('DB_NAME') ?: '',
        ],
        'runway' => [
            'index_root' => 'public/index.php',
            'app_root' => 'app/',
        ],
    ];