<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class StudyScheduleController
{
    public function show(Request $request, string $workspaceId, string $learnerId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $schedule = DB::table('study_schedules')->where([
            'workspace_id' => $workspaceId, 'learner_user_id' => $learnerId,
        ])->first();
        return response()->json(['schedule' => $schedule === null ? null : $this->serialize($schedule)]);
    }

    public function upsert(Request $request, string $workspaceId, string $learnerId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (!$this->memberHasRole($workspaceId, $learnerId, ['learner'])) {
            return response()->json(['message' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }
        $validated = $request->validate([
            'daily_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'guardian_delay_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'guardian_user_id' => ['sometimes', 'uuid'],
            'enabled' => ['required', 'boolean'],
        ]);
        $guardianId = $validated['guardian_user_id'] ?? $request->user()->id;
        if (!$this->memberHasRole($workspaceId, $guardianId, ['owner', 'admin'])) {
            return response()->json(['message' => 'Guardian not found'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $existing = DB::table('study_schedules')->where([
            'workspace_id' => $workspaceId, 'learner_user_id' => $learnerId,
        ])->first();
        $values = [
            'guardian_user_id' => $guardianId,
            'timezone' => $validated['timezone'],
            'daily_time' => $validated['daily_time'],
            'weekdays' => json_encode(array_values(array_unique($validated['weekdays'])), JSON_THROW_ON_ERROR),
            'guardian_delay_minutes' => $validated['guardian_delay_minutes'],
            'enabled' => $validated['enabled'],
            'updated_at' => now(),
        ];
        if ($existing === null) {
            $id = (string)Str::uuid();
            DB::table('study_schedules')->insert($values + [
                'id' => $id, 'workspace_id' => $workspaceId, 'learner_user_id' => $learnerId, 'created_at' => now(),
            ]);
        } else {
            $id = $existing->id;
            DB::table('study_schedules')->where('id', $id)->update($values);
        }
        return response()->json(['schedule' => $this->serialize(DB::table('study_schedules')->where('id', $id)->first())]);
    }

    private function serialize(object $schedule): array
    {
        return [
            'id' => $schedule->id,
            'learner_user_id' => $schedule->learner_user_id,
            'guardian_user_id' => $schedule->guardian_user_id,
            'timezone' => $schedule->timezone,
            'daily_time' => substr((string)$schedule->daily_time, 0, 5),
            'weekdays' => is_string($schedule->weekdays) ? json_decode($schedule->weekdays, true, 512, JSON_THROW_ON_ERROR) : $schedule->weekdays,
            'guardian_delay_minutes' => (int)$schedule->guardian_delay_minutes,
            'enabled' => (bool)$schedule->enabled,
        ];
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return $this->memberHasRole($workspaceId, $request->user()->id, ['owner', 'admin']);
    }

    private function memberHasRole(string $workspaceId, string $userId, array $roles): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $userId, 'status' => 'active',
        ])->whereIn('role', $roles)->exists();
    }
}
