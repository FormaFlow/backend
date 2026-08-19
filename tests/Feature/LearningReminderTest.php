<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Learning\Application\StudyReminderDispatcher;
use FormaFlow\Reminders\Application\PushGateway;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearningReminderTest extends TestCase
{
    public function test_daily_push_is_sent_once_to_learner_then_once_to_guardian_when_missed(): void
    {
        Carbon::setTestNow('2026-08-17 15:30:00');
        // The developer database may contain real local schedules. Keep this
        // dispatcher test scoped to the schedule created below.
        DB::table('study_schedules')->update(['enabled' => false]);
        $owner = UserModel::factory()->create();
        $learner = UserModel::factory()->create(['email' => null, 'account_type' => 'managed_learner']);
        $workspaceId = (string)Str::uuid();
        $now = now();
        DB::table('workspaces')->insert([
            'id' => $workspaceId, 'name' => 'Family', 'slug' => 'push-' . Str::random(8),
            'type' => 'family', 'timezone' => 'Europe/Moscow', 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([[$owner->id, 'owner'], [$learner->id, 'learner']] as [$userId, $role]) {
            DB::table('workspace_memberships')->insert([
                'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $userId,
                'role' => $role, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $assessmentId = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/workspaces/{$workspaceId}/learning/import",
            $this->pack(),
        )->assertCreated()->json('assessment.id');
        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/workspaces/{$workspaceId}/learning/assignments", [
            'assessment_id' => $assessmentId, 'learner_user_id' => $learner->id,
        ])->assertCreated();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/workspaces/{$workspaceId}/learning/schedules/{$learner->id}", [
            'daily_time' => '18:30', 'timezone' => 'Europe/Moscow', 'weekdays' => [1, 2, 3, 4, 5],
            'guardian_delay_minutes' => 60, 'enabled' => true,
        ])->assertOk();
        $scheduleId = DB::table('study_schedules')->where('learner_user_id', $learner->id)->value('id');
        foreach ([[$owner->id, 'owner']] as [$userId, $suffix]) {
            DB::table('push_subscriptions')->insert([
                'id' => (string)Str::uuid(), 'user_id' => $userId,
                'endpoint' => "https://push.example.test/{$suffix}", 'public_key' => 'public',
                'auth_token' => 'auth', 'content_encoding' => 'aes128gcm',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $gateway = new class implements PushGateway {
            public array $deliveries = [];
            public function send(array $subscriptions, array $payload): array
            {
                $this->deliveries[] = ['subscriptions' => $subscriptions, 'payload' => $payload];
                return [];
            }
        };
        $this->app->instance(PushGateway::class, $gateway);
        $dispatcher = $this->app->make(StudyReminderDispatcher::class);

        self::assertSame(0, $dispatcher->dispatchDue());
        self::assertSame('no_subscription', DB::table('learning_notification_log')->where(['schedule_id' => $scheduleId, 'kind' => 'learner_due'])->value('status'));
        DB::table('push_subscriptions')->insert([
            'id' => (string)Str::uuid(), 'user_id' => $learner->id,
            'endpoint' => 'https://push.example.test/learner', 'public_key' => 'public',
            'auth_token' => 'auth', 'content_encoding' => 'aes128gcm',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        self::assertSame(1, $dispatcher->dispatchDue());
        self::assertSame('sent', DB::table('learning_notification_log')->where(['schedule_id' => $scheduleId, 'kind' => 'learner_due'])->value('status'));
        self::assertSame('/learn', $gateway->deliveries[0]['payload']['url']);
        Carbon::setTestNow('2026-08-17 16:31:00');
        self::assertSame(1, $dispatcher->dispatchDue());
        self::assertSame('/admin', $gateway->deliveries[1]['payload']['url']);
        self::assertSame(0, $dispatcher->dispatchDue());
        self::assertCount(2, $gateway->deliveries);
        Carbon::setTestNow();
    }

    private function pack(): array
    {
        return [
            'schema' => 'formaflow.learning-pack.v1',
            'pack' => [
                'external_id' => 'reminder-' . Str::random(6), 'version' => 1, 'title' => 'Короткий тест',
                'description' => 'Original', 'subject' => 'math', 'purpose' => 'practice',
                'target_grade' => 2, 'coverage_from_grade' => 1, 'coverage_to_grade' => 1,
                'source' => ['name' => 'Original', 'type' => 'owned', 'usage_scope' => 'publishable'],
            ],
            'questions' => [[
                'external_id' => 'q1', 'prompt' => '1 + 1', 'type' => 'number',
                'answer_config' => ['accepted' => ['2']], 'points' => 10, 'explanation' => 'Два',
            ]],
        ];
    }
}
