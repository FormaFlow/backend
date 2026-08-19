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

    public function test_owner_can_add_child_credentials_to_email_learner_without_changing_password(): void
    {
        [$owner, $workspaceId, $workspaceSlug] = $this->ownerWorkspace();
        $learner = UserModel::factory()->create([
            'email' => 'alex@example.test',
            'password' => Hash::make('adult-password'),
        ]);
        $originalPassword = $learner->password;
        $now = now();
        $this->app['db']->table('workspace_memberships')->insert([
            'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $learner->id,
            'role' => 'learner', 'status' => 'active', 'managed_by_user_id' => $owner->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->app['db']->table('learner_profiles')->insert([
            'user_id' => $learner->id, 'target_grade' => 9, 'timezone' => 'Europe/Moscow',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learners/{$learner->id}/credentials", [
                'login' => 'alex', 'pin' => '137137',
            ])
            ->assertOk()
            ->assertJsonPath('credentials.login', 'alex');

        self::assertSame($originalPassword, $learner->fresh()->password);
        self::assertTrue(Hash::check('adult-password', $learner->fresh()->password));
        $childLogin = $this->postJson('/api/v1/managed-login', [
            'workspace' => $workspaceSlug, 'login' => 'alex', 'pin' => '137137',
        ])->assertOk()
            ->assertJsonPath('user.id', $learner->id)
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('user.login', 'alex');
        $this->app['auth']->forgetGuards();
        $this->withToken($childLogin->json('token'))
            ->getJson('/api/v1/workspaces')
            ->assertForbidden();
        $this->postJson('/api/v1/login', [
            'email' => 'alex@example.test', 'password' => 'adult-password',
        ])->assertOk()->assertJsonPath('user.id', $learner->id);
    }

    public function test_linked_child_login_must_be_unique_inside_workspace_and_manager_only(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $learners = UserModel::factory()->count(2)->create();
        $now = now();
        foreach ($learners as $learner) {
            $this->app['db']->table('workspace_memberships')->insert([
                'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $learner->id,
                'role' => 'learner', 'status' => 'active', 'managed_by_user_id' => $owner->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learners/{$learners[0]->id}/credentials", [
                'login' => 'shared', 'pin' => '1111',
            ])->assertOk();
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learners/{$learners[1]->id}/credentials", [
                'login' => 'shared', 'pin' => '2222',
            ])->assertUnprocessable()->assertJsonValidationErrors('login');

        $outsider = UserModel::factory()->create();
        $this->actingAs($outsider, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspaceId}/learners/{$learners[1]->id}/credentials", [
                'login' => 'other', 'pin' => '2222',
            ])->assertForbidden();
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

    public function test_guardian_invitation_grants_read_only_progress_access(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $guardian = UserModel::factory()->create(['email' => 'grandfather@example.test']);
        $learner = UserModel::factory()->create();
        $now = now();
        $this->app['db']->table('workspace_memberships')->insert([
            'id' => (string)Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $learner->id,
            'role' => 'learner', 'status' => 'active', 'managed_by_user_id' => $owner->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->app['db']->table('learner_profiles')->insert([
            'user_id' => $learner->id, 'target_grade' => 5, 'timezone' => 'Europe/Moscow',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $token = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/invitations", [
                'email' => 'grandfather@example.test', 'role' => 'guardian',
            ])->assertCreated()->assertJsonPath('invitation.role', 'guardian')->json('token');
        $this->actingAs($guardian, 'sanctum')
            ->postJson('/api/v1/workspaces/invitations/accept', ['token' => $token])
            ->assertOk()->assertJsonPath('role', 'guardian');

        $this->actingAs($guardian, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/progress")
            ->assertOk()->assertJsonPath('learners.0.id', $learner->id);
        $this->actingAs($guardian, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/progress/{$learner->id}")
            ->assertOk();
        $this->actingAs($guardian, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learners", [
                'name' => 'No access', 'login' => 'blocked', 'pin' => '1234', 'target_grade' => 1,
            ])->assertForbidden();
        $this->actingAs($guardian, 'sanctum')
            ->patchJson("/api/v1/workspaces/{$workspaceId}/modules/tutor", ['enabled' => true])
            ->assertForbidden();
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
