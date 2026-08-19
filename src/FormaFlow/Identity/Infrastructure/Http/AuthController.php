<?php

declare(strict_types=1);

namespace FormaFlow\Identity\Infrastructure\Http;

use FormaFlow\Identity\Infrastructure\Http\Resources\UserResource;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use FormaFlow\Workspaces\Application\WorkspaceProvisioner;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function register(Request $request, WorkspaceProvisioner $workspaceProvisioner): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'timezone' => 'sometimes|string|timezone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = UserModel::query()->create([
            'id' => Uuid::generate(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'timezone' => $request->input('timezone', 'Europe/Moscow'),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $workspace = $workspaceProvisioner->provisionFor($user);

        return response()->json([
            'id' => $user->id,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'timezone' => $user->timezone,
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'type' => $workspace->type,
                'timezone' => $workspace->timezone,
                'role' => 'owner',
            ],
        ], Response::HTTP_CREATED);
    }

    public function managedLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace' => 'required|string|max:255',
            'login' => 'required|string|max:32',
            'pin' => 'required|string',
        ]);
        $workspaceSlug = mb_strtolower(trim($validated['workspace']));
        $login = mb_strtolower(trim($validated['login']));
        $key = 'managed-login:' . $request->ip() . ':' . $workspaceSlug . ':' . $login;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $workspace = WorkspaceModel::query()->where('slug', $workspaceSlug)->first();
        $credential = $workspace === null ? null : DB::table('learner_access_credentials')
            ->where('workspace_id', $workspace->id)
            ->where('login_name', $login)
            ->first();
        $membership = $credential === null ? null : WorkspaceMembershipModel::query()
            ->with('user.learnerProfile')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $credential->user_id)
            ->where('role', 'learner')
            ->where('status', 'active')
            ->first();

        if ($membership === null || !Hash::check($validated['pin'], $credential->pin_hash)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => ['The provided credentials are incorrect.']]);
        }
        RateLimiter::clear($key);
        $user = $membership->user;
        $workspace->load('modules');

        return response()->json([
            'token' => $user->createToken('managed-learner-token', ['managed-workspace:' . $workspace->id])->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => null,
                'login' => $credential->login_name,
                'account_type' => $user->account_type,
                'target_grade' => $user->learnerProfile?->target_grade,
                'timezone' => $user->learnerProfile?->timezone ?? $user->timezone,
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'type' => $workspace->type,
                'timezone' => $workspace->timezone,
                'role' => $membership->role,
                'modules' => $workspace->modules->mapWithKeys(
                    static fn($module): array => [$module->module_key => (bool)$module->enabled]
                ),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new UserResource($user));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:2|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'timezone' => 'sometimes|string|timezone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->fill($request->only(['name', 'email', 'timezone']));
        $user->save();

        return response()->json(new UserResource($user));
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
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
    }
}
