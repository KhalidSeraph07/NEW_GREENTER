<?php

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'emisor_h1'),
            'username' => env('DB_USERNAME', 'emisor_h1'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'disable',
        ],
    ],
];
