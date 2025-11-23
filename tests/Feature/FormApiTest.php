<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class FormApiTest extends TestCase
{
    use RefreshDatabase;

    protected ?UserModel $user = null;
    protected string $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->baseUrl = '/api/v1/forms';
    }

    public function test_returns_empty_list_of_forms_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->baseUrl);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'forms' => [],
                'total' => 0,
                'limit' => 15,
                'offset' => 0
            ]);
    }

    public function test_creates_a_new_form_for_authenticated_user_and_returns_id(): void
    {
        $formData = [
            'name' => 'My Test Form',
            'description' => 'A form for testing',
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->baseUrl, $formData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id'])
            ->assertJsonPath('id', fn($id) => str_contains($id, '-'));

        $this->assertDatabaseHas('forms', [
            'user_id' => $this->user->id,
            'name' => 'My Test Form',
            'description' => 'A form for testing',
        ]);
    }

    public function test_fails_to_create_form_without_authentication(): void
    {
        $formData = ['name' => 'Unauthorized Form'];

        $response = $this->postJson($this->baseUrl, $formData);

        $response
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_shows_form_by_id_for_authenticated_user_owner(): void
    {
        $form = FormModel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Visible Form',
            'description' => 'A form for testing',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("{$this->baseUrl}/{$form->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'id' => $form->id,
                'name' => 'Visible Form',
                'description' => 'A form for testing',
                'published' => false,
                'fields_count' => 0,
            ]);
    }

    public function test_fails_to_show_non_existent_form(): void
    {
        $fakeId = (new FormId('fake-uuid-123'))->value();

        $response = $this->actingAs($this->user, 'sanctum')->getJson("{$this->baseUrl}/{$fakeId}");

        $response
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson(['error' => 'Not found']);
    }

    public function test_publishes_an_existing_form(): void
    {
        $form = FormModel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Publishable Form',
        ]);

        DB::table('form_fields')->insert([
            'id' => 'field-1',
            'form_id' => $form->id,
            'name' => 'test',
            'label' => 'Test',
            'type' => 'text',
            'required' => false,
            'options' => null,
            'unit' => null,
            'category' => null,
            'order' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("{$this->baseUrl}/{$form->id}/publish");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['message' => 'Form published']);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'published' => true,
        ]);
    }

    public function test_fails_to_publish_form_without_fields(): void
    {
        $form = FormModel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empty Form',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("{$this->baseUrl}/{$form->id}/publish");

        $response
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['error' => 'Cannot publish form without fields']);
    }

    public function test_adds_a_field_to_existing_form(): void
    {
        $form = FormModel::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Form with Field',
        ]);

        $fieldData = [
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'required' => true,
            'unit' => 'USD',
            'order' => 1,
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson(
            "{$this->baseUrl}/{$form->id}/fields",
            $fieldData
        );

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson(['message' => 'Field added']);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'name' => 'amount',
            'label' => 'Amount',
            'type' => 'number',
            'required' => true,
            'unit' => 'USD',
            'order' => 1,
        ]);
    }

    public function test_fails_to_add_field_with_invalid_type(): void
    {
        $form = FormModel::factory()->create(['user_id' => $this->user->id]);

        $invalidData = [
            'name' => 'invalid',
            'label' => 'Invalid',
            'type' => 'invalid-type', // Not in enum
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson(
            "{$this->baseUrl}/{$form->id}/fields",
            $invalidData
        );

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['type']);
    }
}
