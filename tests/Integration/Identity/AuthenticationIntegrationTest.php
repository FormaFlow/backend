<?php

declare(strict_types=1);

namespace Tests\Integration\Identity;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_email_and_password(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['user' => ['id', 'email']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_user_receives_verification_email_after_registration(): void
    {
        // TODO: Implement email verification logic
        $this->markTestIncomplete('Email verification not implemented yet');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['token', 'user', 'message']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        UserModel::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_refresh_token(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/refresh');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['token']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logout');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_rate_limiting_prevents_brute_force_attacks(): void
    {
        UserModel::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Attempt multiple failed logins
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Next attempt should be rate limited
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    public function test_user_can_verify_email_with_valid_token(): void
    {
        // TODO: Implement email verification token logic
        $this->markTestIncomplete('Email verification not implemented yet');
    }

    public function test_user_profile_shows_verification_status(): void
    {
        $user = UserModel::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'email_verified' => false,
            ]);
    }
}
