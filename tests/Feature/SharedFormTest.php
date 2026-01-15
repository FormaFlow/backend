<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class SharedFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_route_returns_html_with_og_tags_for_published_form(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Form',
            'description' => 'A very public description',
            'published' => true,
        ]);

        $response = $this->get("/shared/{$form->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        // Check OG Tags
        $response->assertSee('<meta property="og:title" content="Public Form"', false);
        $response->assertSee('<meta property="og:description" content="A very public description"', false);

        // Check Redirect Script
        $response->assertSee("window.location.href =", false);
        $response->assertSee($form->id, false);
    }

    public function test_shared_route_returns_404_for_unpublished_form(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'published' => false,
        ]);

        $response = $this->get("/shared/{$form->id}");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_shared_route_returns_404_for_non_existent_form(): void
    {
        $response = $this->get("/shared/non-existent-uuid");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
