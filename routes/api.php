<?php

declare(strict_types=1);

use FormaFlow\Entries\Infrastructure\Http\EntryController;
use FormaFlow\Forms\Infrastructure\Http\FormController;
use FormaFlow\Identity\Infrastructure\Http\AuthController;
use FormaFlow\Reports\Infrastructure\Http\DashboardController;
use FormaFlow\Reports\Infrastructure\Http\ReportController;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

Route::options('{any}', static function () {
    return response('', Response::HTTP_OK);
})->where('any', '.*');

use FormaFlow\Entries\Infrastructure\Http\PublicApiEntryController;
use FormaFlow\Forms\Infrastructure\Http\PublicApiFormController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Public access for shared results
    Route::get('/public/entries/{id}', [PublicApiEntryController::class, 'show']);
    Route::get('/public/forms/{id}', [PublicApiFormController::class, 'show']);
    Route::post('/public/forms/import', [PublicApiFormController::class, 'import']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        Route::prefix('forms')->group(function () {
            Route::get('/', [FormController::class, 'index']);
            Route::post('/', [FormController::class, 'store']);
            Route::get('{id}', [FormController::class, 'show']);
            Route::patch('{id}', [FormController::class, 'update']);
            Route::delete('{id}', [FormController::class, 'destroy']);
            Route::delete('{formId}/fields/{fieldId}', [FormController::class, 'removeField']);
            Route::patch('{formId}/fields/{fieldId}', [FormController::class, 'updateField']);
            Route::post('{id}/publish', [FormController::class, 'publish']);
            Route::post('{id}/fields', [FormController::class, 'addField']);
            Route::post('{id}/entries/import', [FormController::class, 'importEntries']);
        });

        Route::prefix('entries')->group(function () {
            Route::get('/', [EntryController::class, 'index']);
            Route::get('/stats', [EntryController::class, 'stats']);
            Route::get('/{id}', [EntryController::class, 'show']);
            Route::post('/', [EntryController::class, 'store']);
            Route::patch('{id}', [EntryController::class, 'update']);
            Route::delete('{id}', [EntryController::class, 'destroy']);
        });

        Route::prefix('reports')->group(function () {
            Route::post('/', [ReportController::class, 'generate']);
            Route::post('/summary', [ReportController::class, 'summary']);
            Route::post('/multi-time-series', [ReportController::class, 'multiTimeSeries']);
            Route::post('/time-series', [ReportController::class, 'timeSeries']);
            Route::post('/grouped', [ReportController::class, 'grouped']);
            Route::post('/export', [ReportController::class, 'export']);
            Route::get('/weekly-summary', [ReportController::class, 'weeklySummary']);
            Route::get('/monthly-summary', [ReportController::class, 'monthlySummary']);
            Route::get('/predefined/budget', [ReportController::class, 'predefinedBudget']);
            Route::get('/predefined/medicine', [ReportController::class, 'predefinedMedicine']);
            Route::get('/predefined/weight', [ReportController::class, 'predefinedWeight']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/week', [DashboardController::class, 'weekSummary']);
            Route::get('/month', [DashboardController::class, 'monthSummary']);
            Route::get('/trends', [DashboardController::class, 'trends']);
        });
    });
});
