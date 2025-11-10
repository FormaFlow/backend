<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'FormaFlow API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

Route::get('/health-check', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
    ]);
});
