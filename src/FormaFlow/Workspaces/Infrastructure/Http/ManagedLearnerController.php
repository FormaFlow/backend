<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Http;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\LearnerProfileModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ManagedLearnerController
{
    public function index(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $learners = WorkspaceMembershipModel::query()
            ->with(['user.learnerProfile'])
            ->where('workspace_id', $workspaceId)
            ->where('role', 'learner')
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()
            ->map(fn(WorkspaceMembershipModel $membership): array => $this->serialize($membership->user, $workspaceId))
            ->all();

        return response()->json(['learners' => $learners]);
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $workspace = WorkspaceModel::query()->find($workspaceId);
        if ($workspace === null) {
            return response()->json(['message' => 'Workspace not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'login' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
            'target_grade' => 'required|integer|min:1|max:11',
            'timezone' => 'sometimes|string|timezone',
        ]);
        $login = Str::lower($validated['login']);
        $duplicate = DB::table('learner_access_credentials')
            ->where('workspace_id', $workspaceId)
            ->where('login_name', $login)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['login' => ['This login is already used in the workspace.']]);
        }

        $user = DB::transaction(function () use ($request, $workspace, $validated, $login): UserModel {
            $user = UserModel::query()->create([
                'id' => (string)Str::uuid(),
                'name' => $validated['name'],
                'email' => null,
                'login_name' => $login,
                'account_type' => 'managed_learner',
                'password' => Hash::make($validated['pin']),
                'timezone' => $validated['timezone'] ?? $workspace->timezone,
            ]);
            WorkspaceMembershipModel::query()->create([
                'id' => (string)Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'learner',
                'status' => 'active',
                'managed_by_user_id' => $request->user()->id,
            ]);
            LearnerProfileModel::query()->create([
                'user_id' => $user->id,
                'target_grade' => $validated['target_grade'],
                'timezone' => $validated['timezone'] ?? $workspace->timezone,
            ]);
            DB::table('learner_access_credentials')->insert([
                'id' => (string)Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'login_name' => $login,
                'pin_hash' => Hash::make($validated['pin']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $user->load('learnerProfile');
        return response()->json(['learner' => $this->serialize($user, $workspaceId)], Response::HTTP_CREATED);
    }

    public function credentials(Request $request, string $workspaceId, string $learnerId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (!WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId,
            'user_id' => $learnerId,
            'role' => 'learner',
            'status' => 'active',
        ])->exists()) {
            return response()->json(['message' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'login' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
        ]);
        $login = Str::lower($validated['login']);
        if (DB::table('learner_access_credentials')
            ->where('workspace_id', $workspaceId)
            ->where('login_name', $login)
            ->where('user_id', '!=', $learnerId)
            ->exists()) {
            throw ValidationException::withMessages(['login' => ['This login is already used in the workspace.']]);
        }

        DB::transaction(function () use ($workspaceId, $learnerId, $login, $validated): void {
            $credential = DB::table('learner_access_credentials')->where([
                'workspace_id' => $workspaceId, 'user_id' => $learnerId,
            ])->first();
            $values = [
                'login_name' => $login,
                'pin_hash' => Hash::make($validated['pin']),
                'updated_at' => now(),
            ];
            if ($credential === null) {
                DB::table('learner_access_credentials')->insert($values + [
                    'id' => (string)Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'user_id' => $learnerId,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('learner_access_credentials')->where('id', $credential->id)->update($values);
            }
        });

        return response()->json(['credentials' => ['login' => $login]]);
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    private function serialize(UserModel $user, string $workspaceId): array
    {
        $login = DB::table('learner_access_credentials')->where([
            'workspace_id' => $workspaceId, 'user_id' => $user->id,
        ])->value('login_name');
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'login' => $login,
            'target_grade' => $user->learnerProfile?->target_grade,
            'timezone' => $user->learnerProfile?->timezone ?? $user->timezone,
        ];
    }
}
