<?php

return [
    'paths' => [
        // couvre toutes tes routes API (incluant /api/oauth/google/*, /api/login, /api/register, etc.)
        'api/*',
        // garde si tu utilises encore ce cookie ailleurs, sinon tu peux l’enlever
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // EXACTEMENT tes origines front
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', 'http://localhost:5173'),
        env('FRONTEND_URL', 'http://127.0.0.1:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ⚠️ Comme on n’utilise pas de cookies pour l’OAuth (fetch credentials: 'omit'),
    // on met false pour simplifier les CORS.
    'supports_credentials' => false,
];
