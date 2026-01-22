<?php

declare(strict_types=1);

namespace Tests\Integration\Reporting;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class NewReportFeaturesTest extends TestCase
{

    protected UserModel $user;
    protected FormModel $budgetForm;
    protected string $amountId = '00000000-0000-0000-0000-000000000110';
    protected string $otherId = '00000000-0000-0000-0000-000000000113';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create();
        $this->budgetForm = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Budget Tracker',
        ]);

        // Add fields
        DB::table('form_fields')->insert([
            [
                'id' => $this->amountId,
                'form_id' => $this->budgetForm->id,
                'label' => 'Amount',
                'type' => 'currency',
                'unit' => 'USD',
                'required' => false,
                'options' => null,
                'category' => null,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => $this->otherId,
                'form_id' => $this->budgetForm->id,
                'label' => 'Other Value',
                'type' => 'number',
                'unit' => null,
                'required' => false,
                'options' => null,
                'category' => null,
                'order' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $this->createSampleEntries();
    }

    private function createSampleEntries(): void
    {
        $entries = [
            [$this->amountId => 100, $this->otherId => 10, 'date' => '2025-01-01'],
            [$this->amountId => 200, $this->otherId => 20, 'date' => '2025-01-02'],
            [$this->amountId => 300, $this->otherId => 30, 'date' => '2025-01-02'],
        ];

        foreach ($entries as $i => $entry) {
            $data = $entry;
            unset($data['date']);

            DB::table('entries')->insert([
                'id' => "00000000-0000-0000-0000-00000000080{$i}",
                'form_id' => $this->budgetForm->id,
                'user_id' => $this->user->id,
                'data' => json_encode($data),
                'created_at' => Carbon::parse($entry['date']),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function test_summary_endpoint_returns_correct_stats(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/summary', [
                'form_id' => $this->budgetForm->id,
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'total_entries',
                'stats' => [
                    '*' => ['field', 'label', 'type', 'sum', 'avg', 'min', 'max']
                ]
            ]);

        $this->assertEquals(3, $response->json('total_entries'));

        $stats = collect($response->json('stats'));
        $amountStats = $stats->firstWhere('field', $this->amountId);

        $this->assertEquals(600, $amountStats['sum']); // 100+200+300
        $this->assertEquals(200, $amountStats['avg']);
        $this->assertEquals(100, $amountStats['min']);
        $this->assertEquals(300, $amountStats['max']);
    }

    public function test_multi_time_series_endpoint_returns_grouped_data(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/multi-time-series', [
                'form_id' => $this->budgetForm->id,
                'period' => 'daily',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['date', $this->amountId, $this->otherId]
                ],
                'fields'
            ]);

        $data = $response->json('data');
        // Should have 2 entries (2025-01-01 and 2025-01-02)
        $this->assertCount(2, $data);

        $day1 = collect($data)->firstWhere('date', '2025-01-01');
        $this->assertEquals(100, $day1[$this->amountId]);
        $this->assertEquals(10, $day1[$this->otherId]);

        $day2 = collect($data)->firstWhere('date', '2025-01-02');
        $this->assertEquals(500, $day2[$this->amountId]); // 200 + 300
        $this->assertEquals(50, $day2[$this->otherId]); // 20 + 30
    }
}
