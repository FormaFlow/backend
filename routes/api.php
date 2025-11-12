<?php

declare(strict_types=1);

use FormaFlow\Forms\Infrastructure\Http\FormHttpController;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

Route::prefix('v1')->group(function () {
    Route::post('/login', static function (Request $request): Response {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = UserModel::query()->where(['email' => $request->email])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

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

    Route::middleware(['auth:sanctum'])->prefix('forms')->group(function () {
        Route::get('/', [FormHttpController::class, 'index']);
        Route::post('/', [FormHttpController::class, 'store']);
        Route::get('{id}', [FormHttpController::class, 'show']);
        Route::post('{id}/publish', [FormHttpController::class, 'publish']);
        Route::post('{id}/fields', [FormHttpController::class, 'addField']);
    });
});
