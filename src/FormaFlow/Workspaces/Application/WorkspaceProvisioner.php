<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Application;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceModuleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkspaceProvisioner
{
    public function provisionFor(UserModel $user): WorkspaceModel
    {
        $existing = WorkspaceMembershipModel::query()
            ->with('workspace')
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->first();
        if ($existing !== null) {
            return $existing->workspace;
        }

        return DB::transaction(function () use ($user): WorkspaceModel {
            $workspace = WorkspaceModel::query()->create([
                'id' => (string)Str::uuid(),
                'name' => $user->name . ' family',
                'slug' => 'family-' . Str::lower(Str::random(12)),
                'type' => 'family',
                'timezone' => $user->timezone ?: 'Europe/Moscow',
            ]);
            WorkspaceMembershipModel::query()->create([
                'id' => (string)Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
            ]);
            foreach (['learning', 'reminders', 'gamification', 'tutor'] as $module) {
                WorkspaceModuleModel::query()->create([
                    'id' => (string)Str::uuid(),
                    'workspace_id' => $workspace->id,
                    'module_key' => $module,
                    'enabled' => $module !== 'tutor',
                ]);
            }

            return $workspace;
        });
    }
}
