<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Http;

use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class WorkspaceController
{
    public function index(Request $request): JsonResponse
    {
        $memberships = WorkspaceMembershipModel::query()
            ->with(['workspace.modules'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->get();

        return response()->json(['workspaces' => $memberships->map(static fn($membership): array => [
            'id' => $membership->workspace->id,
            'name' => $membership->workspace->name,
            'slug' => $membership->workspace->slug,
            'type' => $membership->workspace->type,
            'timezone' => $membership->workspace->timezone,
            'role' => $membership->role,
            'modules' => $membership->workspace->modules->mapWithKeys(
                static fn($module): array => [$module->module_key => (bool)$module->enabled]
            ),
        ])->values()]);
    }

    public function updateModule(Request $request, string $workspaceId, string $module): JsonResponse
    {
        if (!in_array($module, ['learning', 'reminders', 'gamification', 'tutor'], true)) {
            return response()->json(['message' => 'Module not found'], Response::HTTP_NOT_FOUND);
        }
        $allowed = WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $request->user()->id, 'status' => 'active',
        ])->whereIn('role', ['owner', 'admin'])->exists();
        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);
        $record = DB::table('workspace_modules')->where([
            'workspace_id' => $workspaceId, 'module_key' => $module,
        ])->first();
        $values = [
            'enabled' => $validated['enabled'],
            'config' => isset($validated['config']) ? json_encode($validated['config'], JSON_THROW_ON_ERROR) : null,
            'updated_at' => now(),
        ];
        if ($record === null) {
            DB::table('workspace_modules')->insert($values + [
                'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId,
                'module_key' => $module, 'created_at' => now(),
            ]);
        } else {
            DB::table('workspace_modules')->where('id', $record->id)->update($values);
        }
        return response()->json(['module' => ['key' => $module, 'enabled' => (bool)$validated['enabled']]]);
    }
}
