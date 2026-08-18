<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Reminders\Application\PushGateway;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearningAdminApiTest extends TestCase
{
    public function test_owner_can_list_content_and_get_learner_progress_but_learner_cannot(): void
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
        DB::table('learner_profiles')->insert([
            'user_id' => $learner->id, 'target_grade' => 2, 'timezone' => 'Europe/Moscow',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $assessmentId = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/workspaces/{$workspaceId}/learning/import",
            $this->pack(),
        )->assertCreated()->json('assessment.id');
        $gateway = new class implements PushGateway {
            public array $deliveries = [];
            public function send(array $subscriptions, array $payload): array
            {
                $this->deliveries[] = ['subscriptions' => $subscriptions, 'payload' => $payload];
                return [];
            }
        };
        $this->app->instance(PushGateway::class, $gateway);
        $assignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments", [
                'assessment_id' => $assessmentId,
                'learner_user_id' => $learner->id,
            ])
            ->assertCreated()
            ->assertJsonPath('notification_sent', false)
            ->json('assignment.id');
        self::assertCount(0, $gateway->deliveries);
        $this->actingAs($learner, 'sanctum')->postJson('/api/v1/push/subscriptions', [
            'endpoint' => 'https://push.example.test/learner',
            'keys' => ['p256dh' => 'public', 'auth' => 'auth'],
            'content_encoding' => 'aes128gcm',
        ])->assertCreated();
        self::assertCount(1, $gateway->deliveries);
        self::assertSame('/learn/assignments/' . $assignmentId, $gateway->deliveries[0]['payload']['url']);
        $secondAssignmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments", [
                'assessment_id' => $assessmentId,
                'learner_user_id' => $learner->id,
            ])
            ->assertCreated()
            ->assertJsonPath('notification_sent', true)
            ->json('assignment.id');
        self::assertCount(2, $gateway->deliveries);
        self::assertSame('/learn/assignments/' . $secondAssignmentId, $gateway->deliveries[1]['payload']['url']);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/assessments")
            ->assertOk()
            ->assertJsonPath('assessments.0.id', $assessmentId)
            ->assertJsonPath('assessments.0.title', 'Числа до 10');
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/library")
            ->assertOk()
            ->assertJsonCount(6, 'packs')
            ->assertJsonPath('packs.0.questions', 100);
        $starterId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/library/grade-1-math-foundation-100/install")
            ->assertCreated()
            ->json('assessment.id');
        self::assertSame(100, DB::table('learning_question_metadata')->where('assessment_id', $starterId)->count());
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/editor")
            ->assertOk()
            ->assertJsonPath('assessment.questions.0.answer_config.accepted.0', '5');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/progress")
            ->assertOk()
            ->assertJsonPath('learners.0.id', $learner->id)
            ->assertJsonPath('learners.0.target_grade', 2)
            ->assertJsonPath('learners.0.assignments.total', 2)
            ->assertJsonPath('learners.0.xp_total', 0);
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learning/schedules/{$learner->id}", [
                'daily_time' => '18:30',
                'timezone' => 'Europe/Moscow',
                'weekdays' => [1, 2, 3, 4, 5],
                'guardian_delay_minutes' => 60,
                'enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('schedule.daily_time', '18:30')
            ->assertJsonPath('schedule.weekdays.4', 5)
            ->assertJsonPath('schedule.guardian_user_id', $owner->id);
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/workspaces/{$workspaceId}/modules/tutor", ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('module.enabled', true);

        $this->actingAs($learner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/progress")
            ->assertForbidden();
        $this->actingAs($learner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/editor")
            ->assertForbidden();
        $this->actingAs($learner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learning/schedules/{$learner->id}", [
                'daily_time' => '18:30', 'timezone' => 'Europe/Moscow', 'weekdays' => [1],
                'guardian_delay_minutes' => 60, 'enabled' => true,
            ])->assertForbidden();
    }

    private function pack(): array
    {
        return [
            'schema' => 'formaflow.learning-pack.v1',
            'pack' => [
                'external_id' => 'admin-pack-' . Str::random(6), 'version' => 1,
                'title' => 'Числа до 10', 'description' => 'Original', 'subject' => 'math',
                'purpose' => 'practice', 'target_grade' => 2, 'coverage_from_grade' => 1, 'coverage_to_grade' => 1,
                'source' => ['name' => 'FormaFlow Original', 'type' => 'owned', 'usage_scope' => 'publishable'],
            ],
            'questions' => [[
                'external_id' => 'q1', 'prompt' => '2 + 3', 'type' => 'number',
                'answer_config' => ['accepted' => ['5']], 'points' => 10, 'explanation' => 'Пять',
            ]],
        ];
    }
}
