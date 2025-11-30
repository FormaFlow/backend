<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Symfony\Component\HttpFoundation\Response;

final class EntryApiTest extends TestCase
{
    use RefreshDatabase;

    protected ?UserModel $user = null;
    protected ?FormModel $form = null;
    protected string $baseUrl = '/api/v1/entries';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Test Form',
            'description' => 'A form for entry testing',
        ]);

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
                'category' => 'financial',
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
                'required' => false,
                'unit' => null,
                'options' => json_encode(['values' => ['income', 'expense', 'transfer']]),
                'category' => null,
                'order' => 2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }

    public function test_returns_empty_list_of_entries_for_authenticated_user(): void
    {
        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson($this->baseUrl);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'entries' => [],
                'total' => 0,
                'limit' => 15,
                'offset' => 0,
            ]);
    }

    public function test_creates_new_entry_for_authenticated_user(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 150.50,
                'date' => '2025-01-15',
                'category' => 'income',
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['id', 'form_id', 'data', 'created_at']);

        $this->assertDatabaseHas('entries', [
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_fails_to_create_entry_without_authentication(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => ['amount' => 100],
        ];

        $response = $this->postJson($this->baseUrl, $entryData);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_cannot_create_entry_from_unpublished_form(): void
    {
        $unpublishedForm = FormModel::factory()->forUser($this->user)->create(['published' => false]);

        $entryData = [
            'form_id' => $unpublishedForm->id,
            'data' => ['amount' => 100],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['error' => 'Cannot create entry from unpublished form']);
    }

    public function test_user_can_update_existing_entry(): void
    {
        $entry = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-15', 'category' => 'income'],
        ]);

        $updateData = [
            'data' => [
                'amount' => 200.75,
                'date' => '2025-01-16',
                'category' => 'expense',
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->patchJson("{$this->baseUrl}/{$entry->id}", $updateData);

        $response->assertStatus(Response::HTTP_OK);

        $updated = EntryModel::find($entry->id);
        $this->assertEquals(200.75, $updated->data['amount']);
    }

    public function test_user_cannot_update_another_users_entry(): void
    {
        $otherUser = UserModel::factory()->create();
        $entry = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $otherUser->id,
            'data' => ['amount' => 100],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->patchJson("{$this->baseUrl}/{$entry->id}", ['data' => ['amount' => 200]]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_delete_entry(): void
    {
        $entry = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->deleteJson("{$this->baseUrl}/{$entry->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted('entries', ['id' => $entry->id]);
    }

    public function test_user_cannot_delete_another_users_entry(): void
    {
        $otherUser = UserModel::factory()->create();
        $entry = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $otherUser->id,
            'data' => ['amount' => 100],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->deleteJson("{$this->baseUrl}/{$entry->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_entry_validation_enforces_required_fields(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'date' => '2025-01-15',
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('data.amount');
    }

    public function test_entry_validation_validates_field_types(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 'not-a-number',
                'date' => '2025-01-15',
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('data.amount');
    }

    public function test_entry_validation_validates_date_format(): void
    {
        $entryData = [
            'form_id' => $this->form->id,
            'data' => [
                'amount' => 100,
                'date' => 'invalid-date',
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('data.date');
    }

    public function test_can_filter_entries_by_form_id(): void
    {
        $anotherForm = FormModel::factory()->forUser($this->user)->published()->create();

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        EntryModel::factory()->create([
            'form_id' => $anotherForm->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?form_id={$this->form->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries')
            ->assertJson(fn(AssertableJson $json) => $json->where('entries.0.form_id', $this->form->id)
                ->etc()
            );
    }

    public function test_returns_empty_list_when_no_entries_for_form_id(): void
    {
        $emptyForm = FormModel::factory()->forUser($this->user)->published()->create();

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?form_id={$emptyForm->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(0, 'entries')
            ->assertJson(['total' => 0]);
    }

    public function test_can_filter_entries_by_date_from(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-10'],
            'created_at' => Carbon::parse('2025-01-10'),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200, 'date' => '2025-02-10'],
            'created_at' => Carbon::parse('2025-02-10'),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?date_from=2025-02-01");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_can_filter_entries_by_date_to(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-10'],
            'created_at' => Carbon::parse('2025-01-10'),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200, 'date' => '2025-02-10'],
            'created_at' => Carbon::parse('2025-02-10'),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?date_to=2025-01-31");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_can_filter_entries_by_date_range(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-15'],
            'created_at' => Carbon::parse('2025-01-15'),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200, 'date' => '2025-02-15'],
            'created_at' => Carbon::parse('2025-02-15'),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 300, 'date' => '2025-03-15'],
            'created_at' => Carbon::parse('2025-03-15'),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?date_from=2025-02-01&date_to=2025-02-28");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_can_add_tags_to_entry(): void
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

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response->assertStatus(Response::HTTP_CREATED);

        $entry = EntryModel::where('form_id', $this->form->id)->first();
        $this->assertNotNull($entry);

        $tags = DB::table('entry_tags')->where('entry_id', $entry->id)->pluck('tag')->toArray();
        $this->assertContains('salary', $tags);
        $this->assertContains('monthly', $tags);
        $this->assertContains('recurring', $tags);
    }

    public function test_can_filter_entries_by_single_tag(): void
    {
        $entry1 = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        DB::table('entry_tags')->insert([
            ['entry_id' => $entry1->id, 'tag' => 'important'],
        ]);

        $entry2 = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200],
        ]);

        DB::table('entry_tags')->insert([
            ['entry_id' => $entry2->id, 'tag' => 'optional'],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?tags=important");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_can_filter_entries_by_multiple_tags(): void
    {
        $entry1 = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        DB::table('entry_tags')->insert([
            ['entry_id' => $entry1->id, 'tag' => 'important'],
            ['entry_id' => $entry1->id, 'tag' => 'urgent'],
        ]);

        $entry2 = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200],
        ]);

        DB::table('entry_tags')->insert([
            ['entry_id' => $entry2->id, 'tag' => 'important'],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?tags=important,urgent");

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_can_list_entries_with_default_pagination(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            EntryModel::factory()->create([
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => ['amount' => $i * 10],
            ]);
        }

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson($this->baseUrl);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['entries', 'total', 'limit', 'offset'])
            ->assertJsonCount(15, 'entries')
            ->assertJson(['limit' => 15, 'offset' => 0]);
    }

    public function test_can_list_entries_with_custom_pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            EntryModel::factory()->create([
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => ['amount' => $i * 10],
            ]);
        }

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?limit=10&offset=0");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(10, 'entries')
            ->assertJson(['limit' => 10, 'offset' => 0]);

        $response2 = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?limit=10&offset=10");

        $response2
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(10, 'entries')
            ->assertJson(['limit' => 10, 'offset' => 10]);
    }

    public function test_can_sort_entries_by_amount_ascending(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 300, 'date' => '2025-01-15'],
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-16'],
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200, 'date' => '2025-01-17'],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?sort_by=data.amount&sort_order=asc");

        $response
            ->assertStatus(Response::HTTP_OK);

        $entries = $response->json('entries');
        $this->assertEquals(100, $entries[0]['data']['amount']);
        $this->assertEquals(200, $entries[1]['data']['amount']);
        $this->assertEquals(300, $entries[2]['data']['amount']);
    }

    public function test_can_sort_entries_by_amount_descending(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-15'],
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 300, 'date' => '2025-01-16'],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?sort_by=data.amount&sort_order=desc");

        $response->assertStatus(Response::HTTP_OK);

        $entries = $response->json('entries');
        $this->assertEquals(300, $entries[0]['data']['amount']);
        $this->assertEquals(100, $entries[1]['data']['amount']);
    }

    public function test_can_combine_multiple_filters(): void
    {
        $anotherForm = FormModel::factory()->forUser($this->user)->published()->create();

        $entry1 = EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100, 'date' => '2025-01-15'],
            'created_at' => Carbon::parse('2025-01-15'),
        ]);
        DB::table('entry_tags')->insert(['entry_id' => $entry1->id, 'tag' => 'important']);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 200, 'date' => '2025-02-15'],
            'created_at' => Carbon::parse('2025-02-15'),
        ]);

        EntryModel::factory()->create([
            'form_id' => $anotherForm->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 150, 'date' => '2025-01-20'],
            'created_at' => Carbon::parse('2025-01-20'),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson(
                "{$this->baseUrl}?form_id={$this->form->id}&date_from=2025-01-01&date_to=2025-01-31&tags=important"
            );

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'entries');
    }

    public function test_user_only_sees_their_own_entries(): void
    {
        $otherUser = UserModel::factory()->create();
        $otherForm = FormModel::factory()->forUser($otherUser)->published()->create();

        EntryModel::factory()->count(3)->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        EntryModel::factory()->count(2)->create([
            'form_id' => $otherForm->id,
            'user_id' => $otherUser->id,
            'data' => ['amount' => 200],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson($this->baseUrl);

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(3, 'entries');
    }

    public function test_returns_not_found_for_nonexistent_entry(): void
    {
        $fakeId = 'non-existent-entry-id';

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->patchJson("{$this->baseUrl}/{$fakeId}", ['data' => ['amount' => 100]]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_handles_empty_filters_gracefully(): void
    {
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['amount' => 100],
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?form_id=&tags=&date_from=&date_to=");

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_handles_invalid_date_format_in_filter(): void
    {
        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?date_from=invalid-date");

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_respects_maximum_pagination_limit(): void
    {
        for ($i = 1; $i <= 150; $i++) {
            EntryModel::factory()->create([
                'form_id' => $this->form->id,
                'user_id' => $this->user->id,
                'data' => ['amount' => $i],
            ]);
        }

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}?limit=100");

        $response->assertStatus(Response::HTTP_OK);

        $count = count($response->json('entries'));
        $this->assertLessThanOrEqual(100, $count);

        $this->markTestIncomplete('Must implement maximum pagination limit to 100');
    }
}
