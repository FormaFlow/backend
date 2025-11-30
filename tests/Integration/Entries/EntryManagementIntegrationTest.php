<?php

declare(strict_types=1);

namespace Tests\Integration\Entries;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EntryManagementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UserModel $user;
    protected FormModel $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserModel::factory()->create();
        $this->form = FormModel::factory()->forUser($this->user)->published()->create();

        // Add fields to form
        DB::table('form_fields')->insert([
            [
                'id' => 'field-amount',
                'form_id' => $this->form->id,
                'name' => 'amount',
                'label' => 'Amount',
                'type' => 'currency',
                'required' => true,
                'unit' => 'USD',
                'options' => null,
                'category' => null,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'field-date',
                'form_id' => $this->form->id,
                'name' => 'date',
                'label' => 'Date',
                'type' => 'date',
                'required' => true,
                'unit' => null,
                'options' => null,
                'category' => null,
                'order' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'field-category',
                'form_id' => $this->form->id,
                'name' => 'category',
                'label' => 'Category',
                'type' => 'select',
                'required' => true,
                'unit' => null,
                'options' => null,
                'category' => null,
                'order' => 2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }

    public function test_user_can_create_entry_from_published_form(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 100.50,
                'date' => '2025-01-15',
                'category' => 'income',
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/entries', $entryData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id', 'form_id', 'data', 'created_at']);

        $this->assertDatabaseHas('entries', [
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_entry_from_unpublished_form(): void
    {
        $unpublishedForm = FormModel::factory()->forUser($this->user)->create(['published' => false]);

        $entryData = [
            'form_id' => $unpublishedForm->id,
            'data' => ['amount' => 100],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/entries', $entryData);

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['error' => 'Cannot create entry from unpublished form']);
    }

    public function test_entry_validation_enforces_required_fields(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'date' => '2025-01-15',
                // missing required 'amount' field
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/entries', $entryData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['data.amount']);
    }

    public function test_entry_validation_validates_field_types(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 'not-a-number',
                'date' => '2025-01-15',
                'category' => 'income',
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/entries', $entryData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['data.amount']);
    }

    public function test_user_can_update_existing_entry(): void
    {
        $entry = EntryModel::factory()->create([
            'id' => 'entry-1',
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-15', 'category' => 'income'],
        ]);

        $updateData = [
            'data' => [
                'amount' => 150.75,
                'date' => '2025-01-16',
                'category' => 'expense',
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/entries/{$entry->id}", $updateData);

        $response->assertStatus(Response::HTTP_OK);

        $updated = EntryModel::query()->find($entry->id);
        $this->assertEquals(150.75, $updated->data['amount']);
    }

    public function test_user_can_delete_entry(): void
    {
        $entry = EntryModel::factory()->create([
            'id' => 'entry-to-delete',
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/entries/{$entry->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted('entries', ['id' => $entry->id]);
    }

    public function test_user_can_list_entries_with_pagination(): void
    {
        // Create 25 entries
        for ($i = 1; $i <= 25; $i++) {
            EntryModel::factory()->create([
                'id' => "entry-{$i}",
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => ['amount' => $i * 10],
                'created_at' => Carbon::now()->subDays(25 - $i),
                'updated_at' => Carbon::now(),
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/entries?limit=10&offset=0');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['entries', 'total', 'limit', 'offset'])
            ->assertJsonCount(10, 'entries')
            ->assertJson(['total' => 10, 'limit' => 10]);
    }

    public function test_user_can_filter_entries_by_date_range(): void
    {
        DB::table('entries')->insert([
            [
                'id' => 'entry-jan',
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 100, 'date' => '2025-01-15']),
                'created_at' => Carbon::parse('2025-01-15'),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'entry-feb',
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 200, 'date' => '2025-02-15']),
                'created_at' => Carbon::parse('2025-02-15'),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/entries?date_from=2025-01-01&date_to=2025-01-31');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_user_can_filter_entries_by_form(): void
    {
        $anotherForm = FormModel::factory()->forUser($this->user)->published()->create();

        DB::table('entries')->insert([
            [
                'id' => 'entry-form1',
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 100]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'entry-form2',
                'form_id' => $anotherForm->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 200]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/entries?form_id={$this->form->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_user_can_sort_entries_by_field(): void
    {
        DB::table('entries')->insert([
            [
                'id' => 'entry-1',
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 300, 'date' => '2025-01-15']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'entry-2',
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => json_encode(['amount' => 100, 'date' => '2025-01-16']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/entries?sort_by=data.amount&sort_order=asc');

        $response->assertStatus(Response::HTTP_OK);

        $entries = $response->json('entries');
        $this->assertEquals(100, $entries[0]['data']['amount']);
        $this->assertEquals(300, $entries[1]['data']['amount']);
    }

    public function test_user_can_add_tags_to_entry(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 100,
                'date' => '2025-01-15',
                'category' => 'income',
            ],
            'tags' => ['salary', 'monthly', 'recurring'],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/entries', $entryData);

        $response->assertStatus(Response::HTTP_CREATED);

        $entry = EntryModel::query()->where('form_id', $this->form->id)->first();
        $this->assertNotNull($entry);

        $tags = DB::table('entry_tags')->where('entry_id', $entry->id)->pluck('tag')->toArray();
        $this->assertContains('salary', $tags);
        $this->assertContains('monthly', $tags);
    }

    public function test_user_can_filter_entries_by_tags(): void
    {
        $entry = EntryModel::factory()->create([
            'id' => 'entry-tagged',
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('entry_tags')->insert([
            ['entry_id' => $entry->id, 'tag' => 'important'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/entries?tags=important');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_user_can_bulk_import_entries_from_csv(): void
    {
        $csvContent = "amount,date,category\n100,2025-01-15,income\n200,2025-01-16,expense";

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$this->form->id}/entries/import", [
                'csv_data' => $csvContent,
                'delimiter' => ',',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['imported' => 2, 'errors' => []]);

        $this->assertDatabaseCount('entries', 2);
    }

    public function test_csv_import_validates_data_against_form_schema(): void
    {
        $csvContent = "amount,date,category\ninvalid,2025-01-15,income"; // invalid amount

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/forms/{$this->form->id}/entries/import", [
                'csv_data' => $csvContent,
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson(['imported' => 0]);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
    }

    public function test_entry_audit_log_tracks_changes(): void
    {
        $this->markTestIncomplete('Should implement entry_audit_logs / action_history');

        $entry = EntryModel::factory()->create([
            'id' => 'entry-audit',
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/entries/{$entry->id}", [
                'data' => ['amount' => 200],
            ]);

        $auditLogs = DB::table('entry_audit_logs')
            ->where('entry_id', $entry->id)
            ->get();

        $this->assertNotEmpty($auditLogs);
        $this->assertEquals('update', $auditLogs->first()->action);
    }
}
