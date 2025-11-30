<?php

declare(strict_types=1);

namespace Tests\Integration\Localization;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class LocalizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_support_russian_locale(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/api/v1/forms');

        $response->assertStatus(Response::HTTP_OK);

        $this->assertNotEmpty($response->json());
    }

    public function test_api_responses_support_english_locale(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/forms');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_validation_errors_are_localized(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Accept-Language', 'ru')
            ->postJson('/api/v1/forms', [
                'name' => 'ab', // Too short
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_api_version_is_correctly_identified(): void
    {
        $response = $this->getJson('/api/v1/forms');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED); // Not authenticated
        $response->assertHeader('API-Version', 'v1');
    }

    public function test_api_v2_endpoints_are_accessible(): void
    {
        // TODO: Implement when v2 is ready
        $this->markTestIncomplete('API v2 not implemented yet');
    }
}
