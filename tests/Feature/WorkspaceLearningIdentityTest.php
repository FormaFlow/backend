<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class WorkspaceLearningIdentityTest extends TestCase
{
    public function test_registration_provisions_a_family_workspace_and_learning_modules(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Parent',
            'email' => 'parent@example.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'timezone' => 'Europe/Moscow',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('user.name', 'Parent')
            ->assertJsonPath('workspace.type', 'family')
            ->assertJsonPath('workspace.role', 'owner');

        $workspaceId = $response->json('workspace.id');
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspaceId,
            'user_id' => $response->json('user.id'),
            'role' => 'owner',
        ]);
        foreach (['learning', 'reminders', 'gamification', 'tutor'] as $module) {
            $this->assertDatabaseHas('workspace_modules', [
                'workspace_id' => $workspaceId,
                'module_key' => $module,
            ]);
        }
    }

    public function test_owner_can_create_a_managed_learner_without_email_and_login_with_pin(): void
    {
        [$owner, $workspaceId, $workspaceSlug] = $this->ownerWorkspace();

        $response = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/workspaces/{$workspaceId}/learners",
            [
                'name' => 'Миша',
                'login' => 'misha',
                'pin' => '2468',
                'target_grade' => 2,
                'timezone' => 'Europe/Moscow',
            ],
        );

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('learner.name', 'Миша')
            ->assertJsonPath('learner.email', null)
            ->assertJsonPath('learner.target_grade', 2);

        $learner = UserModel::query()->findOrFail($response->json('learner.id'));
        self::assertTrue(Hash::check('2468', $learner->password));
        self::assertSame('managed_learner', $learner->account_type);

        $login = $this->postJson('/api/v1/managed-login', [
            'workspace' => $workspaceSlug,
            'login' => 'misha',
            'pin' => '2468',
        ]);

        $login->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('user.id', $learner->id)
            ->assertJsonPath('workspace.id', $workspaceId)
            ->assertJsonPath('workspace.role', 'learner')
            ->assertJsonStructure(['token']);
    }

    public function test_non_admin_cannot_create_managed_learners(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $outsider = UserModel::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learners", [
                'name' => 'Child',
                'login' => 'child',
                'pin' => '1234',
                'target_grade' => 5,
            ])
            ->assertStatus(Response::HTTP_FORBIDDEN);

        self::assertNotNull($owner);
    }

    public function test_managed_login_is_rate_limited(): void
    {
        [, , $workspaceSlug] = $this->ownerWorkspace();
        $key = 'managed-login:127.0.0.1:' . $workspaceSlug . ':unknown';
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);

        $this->postJson('/api/v1/managed-login', [
            'workspace' => $workspaceSlug,
            'login' => 'unknown',
            'pin' => '0000',
        ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function test_owner_can_invite_an_existing_adult_to_workspace(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $adult = UserModel::factory()->create(['email' => 'guardian@example.test']);

        $token = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/invitations", [
                'email' => 'guardian@example.test', 'role' => 'admin',
            ])
            ->assertCreated()
            ->assertJsonPath('invitation.role', 'admin')
            ->json('token');

        $this->actingAs($adult, 'sanctum')
            ->postJson('/api/v1/workspaces/invitations/accept', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('workspace_id', $workspaceId);
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspaceId, 'user_id' => $adult->id, 'role' => 'admin', 'status' => 'active',
        ]);
    }

    /** @return array{UserModel, string, string} */
    private function ownerWorkspace(): array
    {
        $owner = UserModel::factory()->create();
        $workspaceId = (string)Str::uuid();
        $workspaceSlug = 'family-' . Str::lower(Str::random(8));
        $now = now();

        $this->app['db']->table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Family',
            'slug' => $workspaceSlug,
            'type' => 'family',
            'timezone' => 'Europe/Moscow',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->app['db']->table('workspace_memberships')->insert([
            'id' => (string)Str::uuid(),
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$owner, $workspaceId, $workspaceSlug];
    }
}
