<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use FormaFlow\Forms\Infrastructure\Http\FormHttpController;

Route::prefix('v1')->group(function () {
    Route::prefix('forms')->group(function () {
        Route::get('/', [FormHttpController::class, 'index']);
        Route::post('/', [FormHttpController::class, 'store']);
        Route::get('{id}', [FormHttpController::class, 'show']);
        Route::post('{id}/publish', [FormHttpController::class, 'publish']);
        Route::post('{id}/fields', [FormHttpController::class, 'addField']);
    });
});
