<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Reminders\Application\PushGateway;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class QuizReminderApiTest extends TestCase
{
    private UserModel $owner;
    private UserModel $recipient;
    private FormModel $quiz;
    private object $pushGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserModel::factory()->create([
            'name' => 'Parent User',
            'email' => 'parent@example.com',
        ]);
        $this->recipient = UserModel::factory()->create([
            'name' => 'Child User',
            'email' => 'child@example.com',
        ]);
        $this->quiz = FormModel::factory()->forUser($this->owner)->published()->create([
            'name' => 'School Quiz',
            'is_quiz' => true,
        ]);
        $this->pushGateway = new class implements PushGateway {
            public array $messages = [];
            public array $expiredEndpoints = [];

            public function send(array $subscriptions, array $payload): array
            {
                $this->messages[] = compact('subscriptions', 'payload');

                return $this->expiredEndpoints;
            }
        };
        $this->app->instance(PushGateway::class, $this->pushGateway);
    }

    public function test_owner_can_configure_quiz_reminder_interval(): void
    {
        $response = $this
            ->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/v1/forms/{$this->quiz->id}", [
                'reminder_interval_minutes' => 180,
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('forms', [
            'id' => $this->quiz->id,
            'reminder_interval_minutes' => 180,
        ]);

        $this
            ->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/forms/{$this->quiz->id}")
            ->assertJsonPath('reminder_interval_minutes', 180);
    }

    public function test_authenticated_user_can_search_recipients_without_exposing_passwords(): void
    {
        UserModel::factory()->create([
            'name' => 'Another User',
            'email' => 'another@example.com',
        ]);

        $response = $this
            ->actingAs($this->owner, 'sanctum')
            ->getJson('/api/v1/users/search?query=child');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $this->recipient->id)
            ->assertJsonPath('users.0.email', 'child@example.com')
            ->assertJsonMissingPath('users.0.password');
    }

    public function test_user_can_register_and_remove_a_pwa_push_subscription(): void
    {
        $subscription = [
            'endpoint' => 'https://push.example.test/subscriptions/device-1',
            'keys' => [
                'p256dh' => 'public-key-value',
                'auth' => 'auth-token-value',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $this
            ->actingAs($this->recipient, 'sanctum')
            ->postJson('/api/v1/push/subscriptions', $subscription)
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->recipient->id,
            'endpoint' => $subscription['endpoint'],
        ]);

        $this
            ->actingAs($this->recipient, 'sanctum')
            ->deleteJson('/api/v1/push/subscriptions', ['endpoint' => $subscription['endpoint']])
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $subscription['endpoint'],
        ]);
    }

    public function test_owner_can_assign_published_quiz_to_recipient_idempotently(): void
    {
        $this->quiz->update(['reminder_interval_minutes' => 120]);
        $this->createPushSubscription();

        $payload = ['user_ids' => [$this->recipient->id, $this->recipient->id]];
        $response = $this
            ->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/forms/{$this->quiz->id}/assignments", $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('assignments.0.recipient.id', $this->recipient->id);

        $this->assertDatabaseCount('quiz_assignments', 1);
        $this->assertDatabaseHas('quiz_assignments', [
            'form_id' => $this->quiz->id,
            'assigner_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'completed_at' => null,
        ]);
        self::assertCount(1, $this->pushGateway->messages);
        self::assertSame('Новый тест: School Quiz', $this->pushGateway->messages[0]['payload']['title']);
        self::assertStringContainsString($this->quiz->id, $this->pushGateway->messages[0]['payload']['url']);
        $nextReminderAt = DB::table('quiz_assignments')->value('next_reminder_at');

        $this
            ->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/forms/{$this->quiz->id}/assignments", $payload)
            ->assertStatus(Response::HTTP_CREATED);
        self::assertCount(1, $this->pushGateway->messages);
        self::assertEquals($nextReminderAt, DB::table('quiz_assignments')->value('next_reminder_at'));

        $otherUser = UserModel::factory()->create();
        $this
            ->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/forms/{$this->quiz->id}/assignments", [
                'user_ids' => [$this->recipient->id],
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_due_reminder_is_completed_without_push_when_recipient_already_submitted(): void
    {
        $assignmentId = '00000000-0000-0000-0000-000000000301';
        DB::table('quiz_assignments')->insert([
            'id' => $assignmentId,
            'form_id' => $this->quiz->id,
            'assigner_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'next_reminder_at' => Carbon::now()->subMinute(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        EntryModel::factory()->create([
            'form_id' => $this->quiz->id,
            'user_id' => $this->recipient->id,
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();

        $assignment = DB::table('quiz_assignments')->where('id', $assignmentId)->first();
        self::assertNotNull($assignment->completed_at);
        self::assertNull($assignment->next_reminder_at);
        self::assertCount(0, $this->pushGateway->messages);
    }

    public function test_due_assignment_sends_push_and_schedules_the_next_reminder(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00');
        $this->quiz->update(['reminder_interval_minutes' => 180]);
        $this->createPushSubscription();
        DB::table('quiz_assignments')->insert([
            'id' => '00000000-0000-0000-0000-000000000302',
            'form_id' => $this->quiz->id,
            'assigner_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'next_reminder_at' => Carbon::now()->subMinute(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();

        self::assertCount(1, $this->pushGateway->messages);
        self::assertSame('Новый тест: School Quiz', $this->pushGateway->messages[0]['payload']['title']);
        $this->assertDatabaseHas('quiz_assignments', [
            'id' => '00000000-0000-0000-0000-000000000302',
            'next_reminder_at' => '2026-07-20 15:00:00',
        ]);
        Carbon::setTestNow();
    }

    public function test_submitting_assigned_quiz_completes_reminders_immediately(): void
    {
        DB::table('quiz_assignments')->insert([
            'id' => '00000000-0000-0000-0000-000000000303',
            'form_id' => $this->quiz->id,
            'assigner_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'next_reminder_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this
            ->actingAs($this->recipient, 'sanctum')
            ->postJson('/api/v1/entries', [
                'form_id' => $this->quiz->id,
                'data' => [],
            ])
            ->assertStatus(Response::HTTP_CREATED);

        $assignment = DB::table('quiz_assignments')
            ->where('id', '00000000-0000-0000-0000-000000000303')
            ->first();
        self::assertNotNull($assignment->completed_at);
        self::assertNull($assignment->next_reminder_at);
    }

    public function test_quiz_reminder_command_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('reminders:send-due')
            ->assertSuccessful();
    }

    public function test_pending_assignment_is_delivered_when_recipient_enables_push_later(): void
    {
        $this
            ->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/forms/{$this->quiz->id}/assignments", [
                'user_ids' => [$this->recipient->id],
            ])
            ->assertStatus(Response::HTTP_CREATED);
        self::assertCount(0, $this->pushGateway->messages);

        $this
            ->actingAs($this->recipient, 'sanctum')
            ->postJson('/api/v1/push/subscriptions', [
                'endpoint' => 'https://push.example.test/subscriptions/new-device',
                'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-token-value'],
            ])
            ->assertStatus(Response::HTTP_CREATED);

        self::assertCount(1, $this->pushGateway->messages);
        $this->assertDatabaseMissing('quiz_assignments', [
            'form_id' => $this->quiz->id,
            'last_notified_at' => null,
        ]);
    }

    public function test_expired_push_subscription_is_removed_after_delivery_attempt(): void
    {
        $this->quiz->update(['reminder_interval_minutes' => 120]);
        $this->createPushSubscription();
        $this->pushGateway->expiredEndpoints = ['https://push.example.test/subscriptions/recipient'];

        $this
            ->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/forms/{$this->quiz->id}/assignments", [
                'user_ids' => [$this->recipient->id],
            ])
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://push.example.test/subscriptions/recipient',
        ]);
        $this->assertDatabaseHas('quiz_assignments', [
            'form_id' => $this->quiz->id,
            'recipient_user_id' => $this->recipient->id,
            'last_notified_at' => null,
        ]);
    }

    private function createPushSubscription(): void
    {
        DB::table('push_subscriptions')->insert([
            'id' => '00000000-0000-0000-0000-000000000401',
            'user_id' => $this->recipient->id,
            'endpoint' => 'https://push.example.test/subscriptions/recipient',
            'public_key' => 'public-key-value',
            'auth_token' => 'auth-token-value',
            'content_encoding' => 'aes128gcm',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
