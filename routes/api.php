<?php

declare(strict_types=1);

use FormaFlow\Entries\Infrastructure\Http\EntryController;
use FormaFlow\Forms\Infrastructure\Http\FormController;
use FormaFlow\Identity\Infrastructure\Http\AuthController;
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

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/login', static function (Request $request): Response {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = UserModel::query()->where(['email' => $request->email])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($key);

        $token = $user->createToken('api-user-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'message' => 'Login successful',
        ]);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
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
    });
});
