<?php

declare(strict_types=1);

namespace Tests\Feature\Entries;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EntriesStatsTest extends TestCase
{
    use RefreshDatabase;

    protected ?UserModel $user = null;
    protected ?FormModel $form = null;
    protected string $baseUrl = '/api/v1/entries';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create();

        $this->form = FormModel::factory()->forUser($this->user)->published()->create();

        DB::table('form_fields')->insert([
            [
                'id' => 'field-amount',
                'form_id' => $this->form->id,
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
                'id' => 'field-qty',
                'form_id' => $this->form->id,
                'label' => 'Quantity',
                'type' => 'number',
                'required' => true,
                'unit' => null,
                'options' => null,
                'category' => null,
                'order' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }

    public function test_can_get_entries_stats(): void
    {
        // Entries for today
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['field-amount' => 100, 'field-qty' => 2],
            'created_at' => Carbon::now(),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['field-amount' => 50, 'field-qty' => 1],
            'created_at' => Carbon::now(),
        ]);

        // Entry for this month but not today
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['field-amount' => 200, 'field-qty' => 5],
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // Entry for another user
        $anotherUser = UserModel::factory()->create();
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $anotherUser->id,
            'data' => ['field-amount' => 1000, 'field-qty' => 10],
            'created_at' => Carbon::now(),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats?form_id={$this->form->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'stats' => [
                    [
                        'field' => '_count',
                        'sum_today' => 2.0,
                        'sum_month' => 3.0,
                    ],
                    [
                        'field' => 'field-amount',
                        'sum_today' => 150.0,
                        'sum_month' => 350.0,
                    ],
                    [
                        'field' => 'field-qty',
                        'sum_today' => 3.0,
                        'sum_month' => 8.0,
                    ],
                ],
            ]);
    }

    public function test_returns_zero_stats_for_form_with_no_numeric_fields(): void
    {
        $formWithNoNumeric = FormModel::factory()->forUser($this->user)->published()->create();

        EntryModel::factory()->create([
            'form_id' => $formWithNoNumeric->id,
            'user_id' => $this->user->id,
            'data' => ['text' => 'some value'],
            'created_at' => Carbon::now(),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats?form_id={$formWithNoNumeric->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'stats' => [
                    [
                        'field' => '_count',
                        'sum_today' => 1.0,
                        'sum_month' => 1.0,
                    ],
                ],
            ]);
    }

    public function test_returns_zero_stats_when_no_entries(): void
    {
        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats?form_id={$this->form->id}");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'stats' => [
                    [
                        'field' => '_count',
                        'sum_today' => 0.0,
                        'sum_month' => 0.0,
                    ],
                ],
            ]);
    }
}