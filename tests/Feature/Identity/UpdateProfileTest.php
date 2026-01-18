<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

final class UpdateProfileTest extends TestCase
{

    public function test_can_update_profile_timezone(): void
    {
        $user = UserModel::factory()->create([
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'timezone' => 'Europe/Moscow',
            ]);

        $response->assertOk();
        $response->assertJsonPath('timezone', 'Europe/Moscow');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'timezone' => 'Europe/Moscow',
        ]);
    }

    public function test_can_update_profile_name_and_email(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'name' => 'New Name',
                'email' => 'newemail@example.com',
            ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'New Name');
        $response->assertJsonPath('email', 'newemail@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'newemail@example.com',
        ]);
    }

    public function test_cannot_update_profile_with_invalid_email(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'email' => 'invalid-email',
            ]);

        $response->assertUnprocessable();
    }
}
