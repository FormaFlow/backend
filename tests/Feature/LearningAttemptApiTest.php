<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearningAttemptApiTest extends TestCase
{
    public function test_assignment_submission_creates_immutable_result_review_xp_and_streak(): void
    {
        Carbon::setTestNow('2026-08-17 09:00:00');
        [$owner, $learner, $workspaceId, $assessmentId] = $this->scenario();

        $assignment = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments", [
                'assessment_id' => $assessmentId,
                'learner_user_id' => $learner->id,
                'due_at' => '2026-08-17T10:00:00+03:00',
            ])
            ->assertCreated();
        $assignmentId = $assignment->json('assignment.id');

        $attempt = $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments/{$assignmentId}/attempts")
            ->assertCreated();
        $attemptId = $attempt->json('attempt.id');
        $questions = $attempt->json('assessment.questions');
        self::assertArrayNotHasKey('answer_config', $questions[0]);

        $response = $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/attempts/{$attemptId}/submit", [
                'idempotency_key' => 'submit-once',
                'responses' => [
                    $questions[0]['id'] => '5',
                    $questions[1]['id'] => '4',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('result.score', 10)
            ->assertJsonPath('result.max_points', 20)
            ->assertJsonPath('result.questions.0.is_correct', true)
            ->assertJsonPath('result.questions.1.is_correct', false)
            ->assertJsonPath('result.xp_total', 20)
            ->assertJsonPath('result.streak.current', 1);

        $this->assertDatabaseHas('learning_assignments', ['id' => $assignmentId, 'status' => 'completed']);
        $this->assertDatabaseHas('learning_attempts', ['id' => $attemptId, 'status' => 'completed', 'score' => 10]);
        $this->assertDatabaseHas('entries', ['user_id' => $learner->id, 'score' => 10]);
        $this->assertDatabaseHas('learning_review_items', [
            'learner_user_id' => $learner->id,
            'question_id' => $questions[1]['id'],
            'stage' => 0,
            'status' => 'due',
        ]);
        self::assertSame(2, DB::table('xp_ledger')->where('learner_user_id', $learner->id)->count());

        DB::table('workspace_modules')->insert([
            'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'module_key' => 'tutor',
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/tutor/explain", [
                'attempt_id' => $attemptId,
                'question_id' => $questions[1]['id'],
                'message' => 'Почему мой ответ неверный?',
            ])
            ->assertOk()
            ->assertJsonPath('tutor.provider', 'mock')
            ->assertJsonCount(3, 'tutor.suggestions');

        $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/attempts/{$attemptId}/submit", [
                'idempotency_key' => 'submit-once',
                'responses' => [$questions[0]['id'] => 'wrong'],
            ])
            ->assertOk()
            ->assertJsonPath('result.score', 10);
        self::assertSame(2, DB::table('learning_responses')->where('attempt_id', $attemptId)->count());
        self::assertSame(2, DB::table('xp_ledger')->where('learner_user_id', $learner->id)->count());
        Carbon::setTestNow();
    }

    public function test_review_item_advances_on_correct_answer_and_resets_after_error(): void
    {
        Carbon::setTestNow('2026-08-17 09:00:00');
        [$owner, $learner, $workspaceId, $assessmentId] = $this->scenario();
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments", [
                'assessment_id' => $assessmentId,
                'learner_user_id' => $learner->id,
            ])->json('assignment.id');
        $attempt = $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments/{$assignmentId}/attempts");
        $questions = $attempt->json('assessment.questions');
        $this->actingAs($learner, 'sanctum')->postJson(
            "/api/v1/workspaces/{$workspaceId}/learning/attempts/" . $attempt->json('attempt.id') . '/submit',
            ['idempotency_key' => 'wrong', 'responses' => [$questions[0]['id'] => '0', $questions[1]['id'] => '0']],
        )->assertOk();

        $reviewId = DB::table('learning_review_items')->where('question_id', $questions[0]['id'])->value('id');
        $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/reviews/{$reviewId}/answer", [
                'idempotency_key' => 'review-1',
                'answer' => '5',
            ])
            ->assertOk()
            ->assertJsonPath('review.stage', 1)
            ->assertJsonPath('review.status', 'scheduled')
            ->assertJsonPath('review.next_review_at', '2026-08-18T09:00:00+00:00');

        Carbon::setTestNow('2026-08-18 09:00:00');
        $this->actingAs($learner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/reviews/{$reviewId}/answer", [
                'idempotency_key' => 'review-2',
                'answer' => 'wrong',
            ])
            ->assertOk()
            ->assertJsonPath('review.stage', 1)
            ->assertJsonPath('review.next_review_at', '2026-08-19T09:00:00+00:00');
        Carbon::setTestNow();
    }

    /** @return array{UserModel, UserModel, string, string} */
    private function scenario(): array
    {
        $owner = UserModel::factory()->create();
        $learner = UserModel::factory()->create(['email' => null, 'account_type' => 'managed_learner']);
        $workspaceId = (string)Str::uuid();
        $now = now();
        DB::table('workspaces')->insert([
            'id' => $workspaceId, 'name' => 'Family', 'slug' => 'family-' . Str::random(8),
            'type' => 'family', 'timezone' => 'Europe/Moscow', 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([[$owner->id, 'owner'], [$learner->id, 'learner']] as [$userId, $role]) {
            DB::table('workspace_memberships')->insert([
                'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $userId,
                'role' => $role, 'status' => 'active', 'managed_by_user_id' => $role === 'learner' ? $owner->id : null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $pack = [
            'schema' => 'formaflow.learning-pack.v1',
            'pack' => [
                'external_id' => 'attempt-pack-' . Str::random(6), 'version' => 1,
                'title' => 'Математика', 'description' => 'Test', 'subject' => 'math',
                'purpose' => 'diagnostic', 'target_grade' => 2,
                'coverage_from_grade' => 1, 'coverage_to_grade' => 1,
                'source' => ['name' => 'Original', 'type' => 'owned', 'usage_scope' => 'publishable'],
            ],
            'questions' => [
                ['external_id' => 'q1', 'prompt' => '2 + 3', 'type' => 'number', 'answer_config' => ['accepted' => ['5']], 'points' => 10, 'explanation' => 'Пять'],
                ['external_id' => 'q2', 'prompt' => 'Больше?', 'type' => 'single_choice', 'options' => [['label' => '4', 'value' => '4'], ['label' => '7', 'value' => '7']], 'answer_config' => ['correct' => ['7']], 'points' => 10, 'explanation' => 'Семь'],
            ],
        ];
        $assessmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $pack)
            ->json('assessment.id');
        return [$owner, $learner, $workspaceId, $assessmentId];
    }
}
