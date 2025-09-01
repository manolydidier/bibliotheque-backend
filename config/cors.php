<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'oauth/*',
        'login',
        'logout',
        'register',
    ],

    'allowed_methods' => ['*'],

    // Laisse uniquement les origines FRONT ici (pas besoin du backend)
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    // Option : patterns pour tolérer n’importe quel port en dev
    'allowed_origins_patterns' => [
        '/^http:\/\/localhost(:\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Si tu utilises des cookies/Sanctum sur d'autres routes :
    'supports_credentials' => true,
];
