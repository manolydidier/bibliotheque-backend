<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',        // Vite frontend
        'http://127.0.0.1:8000', 
        'http://127.0.0.1:5173',      // Laravel backend
        'https://ton-domaine-front.com' // Production
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // Plus permissif pour éviter les problèmes
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // ⚠️ IMPORTANT : doit être true pour Sanctum
];