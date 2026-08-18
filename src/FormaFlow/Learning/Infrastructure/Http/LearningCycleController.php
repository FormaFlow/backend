<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use Carbon\CarbonImmutable;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Learning\Application\LearningAssignmentNotifier;
use FormaFlow\Learning\Domain\QuestionGrader;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentVersionModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class LearningCycleController
{
    public function __construct(
        private QuestionGrader $grader,
        private LearningAssignmentNotifier $assignmentNotifier,
    ) {
    }

    public function createAssignment(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return $this->forbidden();
        }
        $validated = $request->validate([
            'assessment_id' => 'required|uuid',
            'learner_user_id' => 'required|uuid',
            'due_at' => 'sometimes|nullable|date',
        ]);
        $assessment = LearningAssessmentModel::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'published')
            ->find($validated['assessment_id']);
        if ($assessment === null || $assessment->current_version < 1) {
            return response()->json(['message' => 'Published assessment not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->hasRole($workspaceId, $validated['learner_user_id'], ['learner'])) {
            return response()->json(['message' => 'Learner not found'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $version = LearningAssessmentVersionModel::query()
            ->where('assessment_id', $assessment->id)
            ->where('version_number', $assessment->current_version)
            ->firstOrFail();
        $id = (string)Str::uuid();
        DB::table('learning_assignments')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'assessment_id' => $assessment->id,
            'assessment_version_id' => $version->id,
            'learner_user_id' => $validated['learner_user_id'],
            'assigned_by_user_id' => $request->user()->id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'due_at' => $validated['due_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notificationSent = $this->assignmentNotifier->notify($id);

        return response()->json(['assignment' => [
            'id' => $id,
            'assessment_id' => $assessment->id,
            'assessment_version' => $version->version_number,
            'learner_user_id' => $validated['learner_user_id'],
            'status' => 'assigned',
            'due_at' => isset($validated['due_at']) ? CarbonImmutable::parse($validated['due_at'])->toIso8601String() : null,
        ], 'notification_sent' => $notificationSent], Response::HTTP_CREATED);
    }

    public function startAttempt(Request $request, string $workspaceId, string $assignmentId): JsonResponse
    {
        if (!$this->hasRole($workspaceId, $request->user()->id, ['learner'])) {
            return $this->forbidden();
        }
        $assignment = DB::table('learning_assignments')
            ->where('workspace_id', $workspaceId)
            ->where('learner_user_id', $request->user()->id)
            ->where('id', $assignmentId)
            ->first();
        if ($assignment === null) {
            return response()->json(['message' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }
        $existing = DB::table('learning_attempts')
            ->where('assignment_id', $assignmentId)
            ->where('learner_user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->first();
        if ($existing !== null) {
            return response()->json($this->attemptPayload($existing), Response::HTTP_OK);
        }
        if ($assignment->status === 'completed') {
            return response()->json(['message' => 'Assignment already completed'], Response::HTTP_CONFLICT);
        }
        $version = LearningAssessmentVersionModel::query()->findOrFail($assignment->assessment_version_id);
        $attemptId = (string)Str::uuid();
        DB::transaction(function () use ($attemptId, $assignment, $version): void {
            DB::table('learning_attempts')->insert([
                'id' => $attemptId,
                'workspace_id' => $assignment->workspace_id,
                'assignment_id' => $assignment->id,
                'assessment_id' => $assignment->assessment_id,
                'assessment_version_id' => $version->id,
                'learner_user_id' => $assignment->learner_user_id,
                'status' => 'in_progress',
                'max_points' => $version->max_points,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('learning_assignments')->where('id', $assignment->id)->update([
                'status' => 'in_progress', 'updated_at' => now(),
            ]);
        });
        $attempt = DB::table('learning_attempts')->where('id', $attemptId)->first();
        return response()->json($this->attemptPayload($attempt), Response::HTTP_CREATED);
    }

    public function submit(Request $request, string $workspaceId, string $attemptId): JsonResponse
    {
        if (!$this->hasRole($workspaceId, $request->user()->id, ['learner'])) {
            return $this->forbidden();
        }
        $validated = $request->validate([
            'idempotency_key' => 'required|string|max:128',
            'responses' => 'required|array',
        ]);
        $attempt = DB::table('learning_attempts')
            ->where('workspace_id', $workspaceId)
            ->where('learner_user_id', $request->user()->id)
            ->where('id', $attemptId)
            ->first();
        if ($attempt === null) {
            return response()->json(['message' => 'Attempt not found'], Response::HTTP_NOT_FOUND);
        }
        if ($attempt->status === 'completed') {
            return response()->json(['result' => $this->completedResult($attemptId)]);
        }

        DB::transaction(function () use ($attemptId, $validated): void {
            $locked = DB::table('learning_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            if ($locked->status === 'completed') {
                return;
            }
            $version = LearningAssessmentVersionModel::query()->findOrFail($locked->assessment_version_id);
            $snapshot = $version->snapshot;
            $score = 0;
            $entryData = [];
            foreach ($snapshot['questions'] as $question) {
                $questionId = $question['id'];
                $answer = $validated['responses'][$questionId] ?? null;
                $correct = $this->grader->isCorrect($question['type'], $question['answer_config'], $answer);
                $awarded = $correct ? (int)$question['points'] : 0;
                $score += $awarded;
                $entryData[$questionId] = $answer;
                DB::table('learning_responses')->insert([
                    'id' => (string)Str::uuid(),
                    'attempt_id' => $locked->id,
                    'question_id' => $questionId,
                    'answer' => $this->json($answer),
                    'is_correct' => $correct,
                    'points_awarded' => $awarded,
                    'max_points' => (int)$question['points'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($correct) {
                    $this->addXp($locked->workspace_id, $locked->learner_user_id, 'correct_answer', 'attempt_question', $locked->id . ':' . $questionId, $awarded);
                } else {
                    $this->queueReview($locked, $question);
                }
            }
            $entry = EntryModel::query()->create([
                'id' => (string)Str::uuid(),
                'form_id' => LearningAssessmentModel::query()->findOrFail($locked->assessment_id)->form_id,
                'user_id' => $locked->learner_user_id,
                'data' => $entryData,
                'score' => $score,
            ]);
            $this->addXp($locked->workspace_id, $locked->learner_user_id, 'assessment_completed', 'attempt_completion', $locked->id, 10);
            $streak = $this->recordActivity($locked->workspace_id, $locked->learner_user_id);
            DB::table('learning_attempts')->where('id', $locked->id)->update([
                'entry_id' => $entry->id,
                'status' => 'completed',
                'score' => $score,
                'submission_key' => $validated['idempotency_key'],
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('learning_assignments')->where('id', $locked->assignment_id)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->award($locked->workspace_id, $locked->learner_user_id, 'first_steps');
            if ($score === (int)$locked->max_points && $score > 0) {
                $this->award($locked->workspace_id, $locked->learner_user_id, 'perfect_score');
            }
            if ($streak['current'] >= 3) {
                $this->award($locked->workspace_id, $locked->learner_user_id, 'streak_3');
            }
        });

        return response()->json(['result' => $this->completedResult($attemptId)]);
    }

    public function answerReview(Request $request, string $workspaceId, string $reviewId): JsonResponse
    {
        if (!$this->hasRole($workspaceId, $request->user()->id, ['learner'])) {
            return $this->forbidden();
        }
        $validated = $request->validate([
            'idempotency_key' => 'required|string|max:128',
            'answer' => 'present',
        ]);
        $item = DB::table('learning_review_items')->where([
            'id' => $reviewId,
            'workspace_id' => $workspaceId,
            'learner_user_id' => $request->user()->id,
        ])->first();
        if ($item === null) {
            return response()->json(['message' => 'Review item not found'], Response::HTTP_NOT_FOUND);
        }
        DB::transaction(function () use ($reviewId, $validated): void {
            $item = DB::table('learning_review_items')->where('id', $reviewId)->lockForUpdate()->first();
            if (
                DB::table('learning_review_attempts')->where([
                'review_item_id' => $reviewId,
                'idempotency_key' => $validated['idempotency_key'],
                ])->exists()
            ) {
                return;
            }
            $question = $this->decode($item->question_snapshot);
            $correct = $this->grader->isCorrect($question['type'], $question['answer_config'], $validated['answer']);
            if ($correct) {
                $stage = min(4, (int)$item->stage + 1);
                $status = $stage >= 4 ? 'mastered' : 'scheduled';
                $days = [1 => 1, 2 => 3, 3 => 7, 4 => 14][$stage];
                $next = $status === 'mastered' ? null : now()->addDays($days);
                $this->addXp($item->workspace_id, $item->learner_user_id, 'review_correct', 'review_attempt', $reviewId . ':' . $validated['idempotency_key'], 5);
            } else {
                $stage = 1;
                $status = 'scheduled';
                $next = now()->addDay();
            }
            DB::table('learning_review_items')->where('id', $reviewId)->update([
                'stage' => $stage,
                'status' => $status,
                'next_review_at' => $next ?? now()->addDays(36500),
                'last_reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('learning_review_attempts')->insert([
                'id' => (string)Str::uuid(),
                'review_item_id' => $reviewId,
                'idempotency_key' => $validated['idempotency_key'],
                'answer' => $this->json($validated['answer']),
                'is_correct' => $correct,
                'resulting_stage' => $stage,
                'resulting_status' => $status,
                'resulting_next_review_at' => $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $fresh = DB::table('learning_review_items')->where('id', $reviewId)->first();
        return response()->json(['review' => $this->reviewPayload($fresh)]);
    }

    public function today(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->hasRole($workspaceId, $request->user()->id, ['learner'])) {
            return $this->forbidden();
        }
        $assignments = DB::table('learning_assignments as a')
            ->join('learning_assessments as la', 'la.id', '=', 'a.assessment_id')
            ->join('forms as f', 'f.id', '=', 'la.form_id')
            ->where('a.workspace_id', $workspaceId)
            ->where('a.learner_user_id', $request->user()->id)
            ->whereIn('a.status', ['assigned', 'in_progress'])
            ->orderByRaw('a.due_at asc nulls last')
            ->select(['a.id', 'a.status', 'a.due_at', 'la.subject_code', 'f.name as title'])
            ->get();
        $reviewsDue = DB::table('learning_review_items')->where([
            'workspace_id' => $workspaceId,
            'learner_user_id' => $request->user()->id,
        ])->whereIn('status', ['due', 'scheduled'])->where('next_review_at', '<=', now())->count();

        return response()->json([
            'assignments' => $assignments,
            'reviews_due' => $reviewsDue,
            'xp_total' => $this->xpTotal($workspaceId, $request->user()->id),
            'streak' => $this->streak($workspaceId, $request->user()->id),
            'achievements' => DB::table('achievement_awards')->where([
                'workspace_id' => $workspaceId, 'learner_user_id' => $request->user()->id,
            ])->orderBy('awarded_at')->pluck('achievement_code')->all(),
        ]);
    }

    public function dueReviews(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->hasRole($workspaceId, $request->user()->id, ['learner'])) {
            return $this->forbidden();
        }
        $items = DB::table('learning_review_items')->where([
            'workspace_id' => $workspaceId,
            'learner_user_id' => $request->user()->id,
        ])->whereIn('status', ['due', 'scheduled'])->where('next_review_at', '<=', now())
            ->orderBy('next_review_at')->get()->map(fn(object $item): array => $this->reviewPayload($item));
        return response()->json(['reviews' => $items]);
    }

    private function attemptPayload(object $attempt): array
    {
        $version = LearningAssessmentVersionModel::query()->findOrFail($attempt->assessment_version_id);
        $snapshot = $version->snapshot;
        $snapshot['version'] = $version->version_number;
        $snapshot['max_points'] = $version->max_points;
        $snapshot['questions'] = array_map(function (array $question): array {
            unset($question['answer_config'], $question['explanation'], $question['explanation_media_id']);
            $question['prompt_media_url'] = $this->mediaUrl($question['prompt_media_id'] ?? null);
            return $question;
        }, $snapshot['questions']);
        return ['attempt' => [
            'id' => $attempt->id,
            'assignment_id' => $attempt->assignment_id,
            'status' => $attempt->status,
            'started_at' => CarbonImmutable::parse($attempt->started_at)->toIso8601String(),
        ], 'assessment' => $snapshot];
    }

    private function completedResult(string $attemptId): array
    {
        $attempt = DB::table('learning_attempts')->where('id', $attemptId)->first();
        $version = LearningAssessmentVersionModel::query()->findOrFail($attempt->assessment_version_id);
        $responses = DB::table('learning_responses')->where('attempt_id', $attemptId)->get()->keyBy('question_id');
        $questions = [];
        foreach ($version->snapshot['questions'] as $question) {
            $response = $responses->get($question['id']);
            $questions[] = [
                'id' => $question['id'],
                'prompt' => $question['prompt'],
                'prompt_media_url' => $this->mediaUrl($question['prompt_media_id'] ?? null),
                'answer' => $response === null ? null : $this->decode($response->answer),
                'is_correct' => $response !== null && (bool)$response->is_correct,
                'points_awarded' => $response === null ? 0 : (int)$response->points_awarded,
                'max_points' => (int)$question['points'],
                'correct_answer' => $question['answer_config'],
                'explanation' => $question['explanation'] ?? null,
            ];
        }
        return [
            'attempt_id' => $attempt->id,
            'score' => (int)$attempt->score,
            'max_points' => (int)$attempt->max_points,
            'questions' => $questions,
            'xp_total' => $this->xpTotal($attempt->workspace_id, $attempt->learner_user_id),
            'streak' => $this->streak($attempt->workspace_id, $attempt->learner_user_id),
        ];
    }

    private function queueReview(object $attempt, array $question): void
    {
        $existing = DB::table('learning_review_items')->where([
            'workspace_id' => $attempt->workspace_id,
            'learner_user_id' => $attempt->learner_user_id,
            'question_id' => $question['id'],
        ])->first();
        $values = [
            'assessment_id' => $attempt->assessment_id,
            'assessment_version_id' => $attempt->assessment_version_id,
            'question_snapshot' => $this->json($question),
            'stage' => 0,
            'status' => 'due',
            'next_review_at' => now(),
            'updated_at' => now(),
        ];
        if ($existing !== null) {
            DB::table('learning_review_items')->where('id', $existing->id)->update($values);
            return;
        }
        DB::table('learning_review_items')->insert($values + [
            'id' => (string)Str::uuid(),
            'workspace_id' => $attempt->workspace_id,
            'learner_user_id' => $attempt->learner_user_id,
            'question_id' => $question['id'],
            'created_at' => now(),
        ]);
    }

    private function addXp(string $workspaceId, string $learnerId, string $reason, string $sourceType, string $sourceId, int $points): void
    {
        if ($points < 1) {
            return;
        }
        DB::table('xp_ledger')->insertOrIgnore([
            'id' => (string)Str::uuid(),
            'workspace_id' => $workspaceId,
            'learner_user_id' => $learnerId,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'points' => $points,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordActivity(string $workspaceId, string $learnerId): array
    {
        $record = DB::table('learner_streaks')->where(['workspace_id' => $workspaceId, 'learner_user_id' => $learnerId])->first();
        $today = now()->toDateString();
        if ($record === null) {
            DB::table('learner_streaks')->insert([
                'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'learner_user_id' => $learnerId,
                'current_streak' => 1, 'longest_streak' => 1, 'last_activity_date' => $today,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['current' => 1, 'longest' => 1];
        }
        $last = $record->last_activity_date === null ? null : CarbonImmutable::parse($record->last_activity_date);
        $current = $last?->toDateString() === $today
            ? (int)$record->current_streak
            : ($last?->isSameDay(now()->subDay()) ? (int)$record->current_streak + 1 : 1);
        $longest = max((int)$record->longest_streak, $current);
        DB::table('learner_streaks')->where('id', $record->id)->update([
            'current_streak' => $current, 'longest_streak' => $longest,
            'last_activity_date' => $today, 'updated_at' => now(),
        ]);
        return ['current' => $current, 'longest' => $longest];
    }

    private function award(string $workspaceId, string $learnerId, string $code): void
    {
        DB::table('achievement_awards')->insertOrIgnore([
            'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'learner_user_id' => $learnerId,
            'achievement_code' => $code, 'awarded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function reviewPayload(object $item): array
    {
        $question = $this->decode($item->question_snapshot);
        unset($question['answer_config']);
        $question['prompt_media_url'] = $this->mediaUrl($question['prompt_media_id'] ?? null);
        return [
            'id' => $item->id,
            'question' => $question,
            'stage' => (int)$item->stage,
            'status' => $item->status,
            'next_review_at' => $item->status === 'mastered' ? null : CarbonImmutable::parse($item->next_review_at)->toIso8601String(),
        ];
    }

    private function xpTotal(string $workspaceId, string $learnerId): int
    {
        return (int)DB::table('xp_ledger')->where(['workspace_id' => $workspaceId, 'learner_user_id' => $learnerId])->sum('points');
    }

    private function streak(string $workspaceId, string $learnerId): array
    {
        $record = DB::table('learner_streaks')->where(['workspace_id' => $workspaceId, 'learner_user_id' => $learnerId])->first();
        return ['current' => (int)($record->current_streak ?? 0), 'longest' => (int)($record->longest_streak ?? 0)];
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return $this->hasRole($workspaceId, $request->user()->id, ['owner', 'admin']);
    }

    private function hasRole(string $workspaceId, string $userId, array $roles): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $userId, 'status' => 'active',
        ])->whereIn('role', $roles)->exists();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function decode(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
    }

    private function mediaUrl(?string $assetId): ?string
    {
        if ($assetId === null) {
            return null;
        }
        $asset = DB::table('media_assets')->where('id', $assetId)->first(['disk', 'path']);
        return $asset === null ? null : Storage::disk($asset->disk)->url($asset->path);
    }
}
