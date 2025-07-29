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

];