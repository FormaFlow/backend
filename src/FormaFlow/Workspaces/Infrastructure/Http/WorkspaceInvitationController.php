<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Http;

use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class WorkspaceInvitationController
{
    public function index(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $invitations = DB::table('workspace_invitations')->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')->get(['id', 'email', 'role', 'expires_at', 'accepted_at']);
        return response()->json(['invitations' => $invitations]);
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,guardian,member'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $token = Str::random(64);
        $id = (string)Str::uuid();
        DB::table('workspace_invitations')->where([
            'workspace_id' => $workspaceId, 'email' => $email,
        ])->whereNull('accepted_at')->delete();
        DB::table('workspace_invitations')->insert([
            'id' => $id, 'workspace_id' => $workspaceId, 'email' => $email,
            'role' => $validated['role'], 'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $request->user()->id, 'expires_at' => now()->addDays(7),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json([
            'invitation' => ['id' => $id, 'email' => $email, 'role' => $validated['role'], 'expires_at' => now()->addDays(7)->toIso8601String()],
            'token' => $token,
            'accept_url' => rtrim((string)config('app.frontend_url', config('app.url')), '/') . '/accept-invitation?token=' . $token,
        ], Response::HTTP_CREATED);
    }

    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $invitation = DB::table('workspace_invitations')->where('token_hash', hash('sha256', $validated['token']))
            ->whereNull('accepted_at')->where('expires_at', '>', now())->first();
        if ($invitation === null || mb_strtolower((string)$request->user()->email) !== $invitation->email) {
            return response()->json(['message' => 'Invitation is invalid or expired'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        DB::transaction(function () use ($invitation, $request): void {
            $membership = DB::table('workspace_memberships')->where([
                'workspace_id' => $invitation->workspace_id, 'user_id' => $request->user()->id,
            ])->first();
            if ($membership === null) {
                DB::table('workspace_memberships')->insert([
                    'id' => (string)Str::uuid(), 'workspace_id' => $invitation->workspace_id,
                    'user_id' => $request->user()->id, 'role' => $invitation->role, 'status' => 'active',
                    'updated_at' => now(), 'created_at' => now(),
                ]);
            } else {
                DB::table('workspace_memberships')->where('id', $membership->id)->update([
                    'role' => $invitation->role, 'status' => 'active', 'updated_at' => now(),
                ]);
            }
            DB::table('workspace_invitations')->where('id', $invitation->id)->update([
                'accepted_at' => now(), 'updated_at' => now(),
            ]);
        });
        return response()->json(['workspace_id' => $invitation->workspace_id, 'role' => $invitation->role]);
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $request->user()->id, 'status' => 'active',
        ])->whereIn('role', ['owner', 'admin'])->exists();
    }
}
