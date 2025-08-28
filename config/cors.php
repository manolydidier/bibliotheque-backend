<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'oauth/*',
        'login',
        'logout',
        'register'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173', // L'origine de votre frontend
        'http://127.0.0.1:5173', // L'origine de votre frontend (si vous l'utilisez)
        'http://127.0.0.1:8000', // Backend local
        'https://ton-domaine-front.com', // Autre domaine front
        env('FRONTEND_URL', 'http://127.0.0.1:5173')
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Important pour Sanctum
];


