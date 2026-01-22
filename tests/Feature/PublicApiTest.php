<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PublicApiTest extends TestCase
{

    public function test_public_entry_api_returns_entry_data(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'published' => true,
        ]);

        $entryId = '00000000-0000-0000-0000-000000000120';

        DB::table('entries')->insert([
            'id' => $entryId,
            'form_id' => $form->id,
            'user_id' => $user->id,
            'data' => json_encode(['field' => 'value']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'score' => 10,
            'duration' => 120,
        ]);

        $response = $this->getJson("/api/v1/public/entries/{$entryId}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'id' => $entryId,
                'form_id' => $form->id,
                'score' => 10,
                'duration' => 120,
            ]);
    }

    public function test_public_form_api_returns_published_form_data(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Form',
            'published' => true,
        ]);

        $response = $this->getJson("/api/v1/public/forms/{$form->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'id' => $form->id,
                'name' => 'Public Form',
            ]);
    }

    public function test_public_form_api_returns_404_for_unpublished_form(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'published' => false,
        ]);

        $response = $this->getJson("/api/v1/public/forms/{$form->id}");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_shared_result_route_returns_html_with_og_tags(): void
    {
        $user = UserModel::factory()->create();
        $form = FormModel::factory()->create([
            'user_id' => $user->id,
            'name' => 'Quiz Form',
            'is_quiz' => true,
            'published' => true,
        ]);

        // Add a field with points to calculate total score
        DB::table('form_fields')->insert([
            'id' => '00000000-0000-0000-0000-000000000130',
            'form_id' => $form->id,
            'label' => 'Q1',
            'type' => 'text',
            'required' => true,
            'order' => 0,
            'points' => 10,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $entryId = '00000000-0000-0000-0000-000000000121';
        DB::table('entries')->insert([
            'id' => $entryId,
            'form_id' => $form->id,
            'user_id' => $user->id,
            'data' => json_encode(['00000000-0000-0000-0000-000000000130' => 'answer']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'score' => 5,
        ]);

        $response = $this->get("/shared/result/{$entryId}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertSee('<meta property="og:title" content="I scored 5 / 10 in Quiz Form!"', false);
    }

    public function test_imports_form_via_public_api(): void
    {
        // Ensure at least one user exists
        $user = UserModel::factory()->create();

        $formData = [
            'name' => 'Imported Quiz',
            'description' => 'A quiz from JSON',
            'is_quiz' => true,
            'published' => true,
            'fields' => [
                [
                    'label' => 'Question 1',
                    'type' => 'text',
                    'required' => true,
                    'points' => 10
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/public/forms/import', $formData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('forms', [
            'name' => 'Imported Quiz',
            'is_quiz' => true,
            'published' => true,
        ]);

        $this->assertDatabaseHas('form_fields', [
            'label' => 'Question 1',
            'points' => 10,
        ]);
    }
}
