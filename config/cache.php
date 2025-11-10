<?php

declare(strict_types=1);

return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'formaflow'),
];
