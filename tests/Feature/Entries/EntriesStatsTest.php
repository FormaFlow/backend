<?php

declare(strict_types=1);

namespace Tests\Feature\Entries;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class EntriesStatsTest extends TestCase
{
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
                'id' => '00000000-0000-0000-0000-000000000110',
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
                'id' => '00000000-0000-0000-0000-000000000111',
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
            'data' => ['00000000-0000-0000-0000-000000000110' => 100, '00000000-0000-0000-0000-000000000111' => 2],
            'created_at' => Carbon::now(),
        ]);

        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 50, '00000000-0000-0000-0000-000000000111' => 1],
            'created_at' => Carbon::now(),
        ]);

        // Entry for this month but not today
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 200, '00000000-0000-0000-0000-000000000111' => 5],
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // Entry for another user
        $anotherUser = UserModel::factory()->create();
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $anotherUser->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 1000, '00000000-0000-0000-0000-000000000111' => 10],
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
                        'field' => '00000000-0000-0000-0000-000000000110',
                        'sum_today' => 150.0,
                        'sum_month' => 350.0,
                    ],
                    [
                        'field' => '00000000-0000-0000-0000-000000000111',
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

    public function test_can_get_entries_stats_by_date(): void
    {
        $yesterday = Carbon::yesterday();
        $threeDaysAgo = Carbon::now()->subDays(3);

        // Entry for yesterday
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 100],
            'created_at' => $yesterday,
        ]);

        // Entry for 3 days ago (same month)
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 50],
            'created_at' => $threeDaysAgo,
        ]);

        // Request stats for yesterday
        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats?form_id={$this->form->id}&date=" . $yesterday->toDateString());

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'stats' => [
                    [
                        'field' => '_count',
                        'sum_today' => 1.0,
                        'sum_month' => 2.0,
                    ],
                    [
                        'field' => '00000000-0000-0000-0000-000000000110',
                        'sum_today' => 100.0,
                        'sum_month' => 150.0,
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

    public function test_returns_daily_stats_for_a_week_in_one_response(): void
    {
        Carbon::setTestNow('2026-07-24 12:00:00');
        EntryModel::factory()->create([
            'form_id' => $this->form->id,
            'user_id' => $this->user->id,
            'data' => ['00000000-0000-0000-0000-000000000110' => 25],
            'created_at' => Carbon::parse('2026-07-22 09:00:00', 'Europe/Moscow'),
        ]);

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats/week?form_id={$this->form->id}&date=2026-07-24");

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.date', '2026-07-24')
            ->assertJsonPath('days.0.stats.0.field', '_count')
            ->assertJsonPath('days.0.stats.0.sum', 0)
            ->assertJsonPath('days.2.date', '2026-07-22')
            ->assertJsonPath('days.2.stats.0.sum', 1)
            ->assertJsonPath('months.2026-07.0.field', '_count')
            ->assertJsonPath('months.2026-07.0.sum_month', 1);

        Carbon::setTestNow();
    }

    public function test_sums_numeric_values_from_an_opted_in_select_field(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');
        $selectForm = FormModel::factory()->forUser($this->user)->published()->create();
        $fieldId = '00000000-0000-0000-0000-000000000112';

        DB::table('form_fields')->insert([
            'id' => $fieldId,
            'form_id' => $selectForm->id,
            'label' => 'Size value',
            'type' => 'select',
            'sum_values' => true,
            'required' => true,
            'options' => json_encode([
                ['label' => 'S', 'value' => '10'],
                ['label' => 'M', 'value' => '20'],
                ['label' => 'XL', 'value' => '40'],
            ], JSON_THROW_ON_ERROR),
            'order' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        foreach ([10, 20] as $value) {
            EntryModel::factory()->create([
                'form_id' => $selectForm->id,
                'user_id' => $this->user->id,
                'data' => [$fieldId => (string)$value],
                'created_at' => Carbon::now(),
            ]);
        }
        EntryModel::factory()->create([
            'form_id' => $selectForm->id,
            'user_id' => $this->user->id,
            'data' => [$fieldId => '40'],
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats?form_id={$selectForm->id}&date=2026-07-29")
            ->assertOk()
            ->assertJsonPath('stats.1.field', $fieldId)
            ->assertJsonPath('stats.1.sum_today', 30)
            ->assertJsonPath('stats.1.sum_month', 70);

        $this
            ->actingAs($this->user, 'sanctum')
            ->getJson("{$this->baseUrl}/stats/week?form_id={$selectForm->id}&date=2026-07-29")
            ->assertOk()
            ->assertJsonPath('days.0.stats.1.field', $fieldId)
            ->assertJsonPath('days.0.stats.1.sum', 30)
            ->assertJsonPath('months.2026-07.1.sum_month', 70);

        Carbon::setTestNow();
    }
}
