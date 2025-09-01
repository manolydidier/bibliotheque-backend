<?php

return [
    'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    
    'api' => [
        'driver' => 'sanctum',  // parfait, Sanctum comme driver api
        'provider' => 'users',
        'hash' => false,
    ],
],

        'passwords' => [
            'users' => [
                'provider' => 'users',
                'table'    => 'password_reset_tokens',
                'expire'   => 60,   // minutes
                'throttle' => 60,   // anti-spam (minutes)
            ],
        ],
];