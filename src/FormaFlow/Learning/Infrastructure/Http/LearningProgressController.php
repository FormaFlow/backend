<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class LearningProgressController
{
    public function index(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $memberships = WorkspaceMembershipModel::query()
            ->with('user.learnerProfile')
            ->where(['workspace_id' => $workspaceId, 'role' => 'learner', 'status' => 'active'])
            ->orderBy('created_at')
            ->get();
        $learners = $memberships->map(function (WorkspaceMembershipModel $membership) use ($workspaceId): array {
            $user = $membership->user;
            $assignments = DB::table('learning_assignments')->where([
                'workspace_id' => $workspaceId, 'learner_user_id' => $user->id,
            ]);
            $attempts = DB::table('learning_attempts')->where([
                'workspace_id' => $workspaceId, 'learner_user_id' => $user->id, 'status' => 'completed',
            ]);
            $streak = DB::table('learner_streaks')->where([
                'workspace_id' => $workspaceId, 'learner_user_id' => $user->id,
            ])->first();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login_name,
                'target_grade' => $user->learnerProfile?->target_grade,
                'assignments' => [
                    'total' => (clone $assignments)->count(),
                    'completed' => (clone $assignments)->where('status', 'completed')->count(),
                    'overdue' => (clone $assignments)->whereNot('status', 'completed')->where('due_at', '<', now())->count(),
                ],
                'attempts_completed' => (clone $attempts)->count(),
                'average_percent' => $this->averagePercent((clone $attempts)->get(['score', 'max_points'])),
                'reviews_due' => DB::table('learning_review_items')->where([
                    'workspace_id' => $workspaceId, 'learner_user_id' => $user->id,
                ])->whereIn('status', ['due', 'scheduled'])->where('next_review_at', '<=', now())->count(),
                'xp_total' => (int)DB::table('xp_ledger')->where([
                    'workspace_id' => $workspaceId, 'learner_user_id' => $user->id,
                ])->sum('points'),
                'streak' => [
                    'current' => (int)($streak->current_streak ?? 0),
                    'longest' => (int)($streak->longest_streak ?? 0),
                ],
                'last_activity_at' => (clone $attempts)->max('completed_at'),
            ];
        })->values();

        return response()->json(['learners' => $learners]);
    }

    public function timeline(Request $request, string $workspaceId, string $learnerId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (
            !WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $learnerId, 'role' => 'learner', 'status' => 'active',
            ])->exists()
        ) {
            return response()->json(['message' => 'Learner not found'], Response::HTTP_NOT_FOUND);
        }
        $attempts = DB::table('learning_attempts as a')
            ->join('learning_assessments as la', 'la.id', '=', 'a.assessment_id')
            ->join('forms as f', 'f.id', '=', 'la.form_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.learner_user_id', $learnerId)
            ->where('a.status', 'completed')
            ->orderByDesc('a.completed_at')
            ->select(['a.id', 'f.name as assessment_title', 'la.subject_code', 'a.score', 'a.max_points', 'a.completed_at'])
            ->get();
        $assignments = DB::table('learning_assignments as a')
            ->join('learning_assessments as la', 'la.id', '=', 'a.assessment_id')
            ->join('forms as f', 'f.id', '=', 'la.form_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.learner_user_id', $learnerId)
            ->orderByDesc('a.assigned_at')
            ->select(['a.id', 'a.status', 'a.assigned_at', 'a.due_at', 'a.completed_at', 'f.name as assessment_title', 'la.subject_code'])
            ->get()
            ->map(function (object $assignment): array {
                $assignmentAttempts = DB::table('learning_attempts')
                    ->where('assignment_id', $assignment->id)
                    ->orderByDesc('started_at')
                    ->get(['id', 'status', 'score', 'max_points', 'started_at', 'completed_at']);
                return (array)$assignment + ['attempts' => $assignmentAttempts];
            });
        return response()->json(['assignments' => $assignments, 'attempts' => $attempts]);
    }

    private function averagePercent(iterable $attempts): int
    {
        $sum = 0.0;
        $count = 0;
        foreach ($attempts as $attempt) {
            if ((int)$attempt->max_points < 1) {
                continue;
            }
            $sum += ((int)$attempt->score / (int)$attempt->max_points) * 100;
            $count++;
        }
        return $count === 0 ? 0 : (int)round($sum / $count);
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $request->user()->id, 'status' => 'active',
        ])->whereIn('role', ['owner', 'admin'])->exists();
    }
}
