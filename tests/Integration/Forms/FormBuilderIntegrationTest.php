<?php

declare(strict_types=1);

namespace Tests\Integration\Forms;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class FormBuilderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserModel::factory()->create();
    }

    public function test_user_can_create_form_template(): void
    {
        $formData = [
            'name' => 'Budget Tracker',
            'description' => 'Track income and expenses',
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/forms', $formData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('forms', [
            'user_id' => $this->user->id,
            'name' => 'Budget Tracker',
            'published' => false,
        ]);
    }

    public function test_user_can_add_text_field_to_form(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Description',
            'type' => 'text',
            'required' => true,
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'type' => 'text',
        ]);
    }

    public function test_user_can_add_number_field_with_currency_type(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Amount',
            'type' => 'currency',
            'required' => true,
            'unit' => 'USD',
            'options' => ['min' => 0, 'max' => 999999.99],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'type' => 'currency',
            'unit' => 'USD',
        ]);
    }

    public function test_user_can_add_date_field_to_form(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Transaction Date',
            'type' => 'date',
            'required' => true,
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_user_can_add_select_field_with_options(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Category',
            'type' => 'select',
            'required' => true,
            'options' => [
                'values' => ['income', 'expense', 'transfer'],
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_user_can_add_boolean_field_to_form(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Is Recurring',
            'type' => 'boolean',
            'required' => false,
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_form_fields_can_have_custom_validation_rules(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Email Address',
            'type' => 'email',
            'required' => true,
            'options' => [
                'validation' => ['email', 'max:255'],
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);
    }

    public function test_user_can_set_field_order(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $field1 = ['label' => 'Field 1', 'type' => 'text', 'order' => 1];
        $field2 = ['label' => 'Field 2', 'type' => 'text', 'order' => 2];
        $field3 = ['label' => 'Field 3', 'type' => 'text', 'order' => 3];

        foreach ([$field1, $field2, $field3] as $fieldData) {
            $this
                ->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);
        }

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/forms/{$form->id}");

        $fields = $response->json('fields');
        $this->assertNotNull($fields);
        $this->assertEquals(1, $fields[0]['order']);
        $this->assertEquals(2, $fields[1]['order']);
        $this->assertEquals(3, $fields[2]['order']);
    }

    public function test_user_can_add_field_categories_and_tags(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $fieldData = [
            'label' => 'Amount',
            'type' => 'currency',
            'category' => 'financial',
            'options' => [
                'tags' => ['money', 'transaction'],
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/fields", $fieldData);

        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'category' => 'financial',
        ]);
    }

    public function test_user_can_publish_form_with_at_least_one_field(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        DB::table('form_fields')->insert([
            'id' => 'field-1',
            'form_id' => $form->id,
            'label' => 'Test',
            'type' => 'text',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/publish");

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'published' => true,
        ]);
    }

    public function test_cannot_publish_form_without_fields(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create();

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$form->id}/publish");

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['error' => 'Cannot publish form without fields']);
    }

    public function test_published_form_increments_version_on_update(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create(['version' => 1]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/forms/{$form->id}", [
                'name' => 'Updated Form Name',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'version' => 2,
        ]);
    }

    public function test_user_can_edit_unpublished_form_without_version_increment(): void
    {
        $form = FormModel::factory()->forUser($this->user)->create(['version' => 1]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/forms/{$form->id}", [
                'name' => 'Updated Form Name',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('forms', [
            'id' => $form->id,
            'version' => 1,
        ]);
    }

    public function test_user_can_list_all_their_forms(): void
    {
        FormModel::factory()->forUser($this->user)->count(3)->create();
        $otherUser = UserModel::factory()->create();
        FormModel::factory()->forUser($otherUser)->create();

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/forms');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(3, 'forms');
    }

    public function test_user_cannot_access_forms_from_other_users(): void
    {
        $otherUser = UserModel::factory()->create();
        $otherForm = FormModel::factory()->forUser($otherUser)->create();

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/forms/{$otherForm->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}
