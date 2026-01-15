<?php

declare(strict_types=1);

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Http\PublicFormController;
use Illuminate\Support\Facades\Route;

Route::get('/shared/{id}', [PublicFormController::class, 'show'])->name('forms.shared');

Route::get('/', static function () {
    return response()->json([
        'message' => 'FormaFlow API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

Route::get('/health-check', static function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => Carbon::now(),
    ]);
});
