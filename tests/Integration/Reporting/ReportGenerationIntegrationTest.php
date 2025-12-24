<?php

declare(strict_types=1);

namespace Tests\Integration\Reporting;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ReportGenerationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UserModel $user;
    protected FormModel $budgetForm;

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
                'id' => 'field-amount',
                'form_id' => $this->budgetForm->id,
                'name' => 'amount',
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
                'id' => 'field-category',
                'form_id' => $this->budgetForm->id,
                'name' => 'category',
                'label' => 'Category',
                'type' => 'select',
                'required' => false,
                'unit' => null,
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
            ['amount' => 1000, 'category' => 'income', 'date' => '2025-01-05'],
            ['amount' => 200, 'category' => 'expense', 'date' => '2025-01-10'],
            ['amount' => 1500, 'category' => 'income', 'date' => '2025-01-15'],
            ['amount' => 300, 'category' => 'expense', 'date' => '2025-01-20'],
            ['amount' => 2000, 'category' => 'income', 'date' => '2025-02-05'],
            ['amount' => 500, 'category' => 'expense', 'date' => '2025-02-10'],
        ];

        foreach ($entries as $i => $entry) {
            DB::table('entries')->insert([
                'id' => "entry-{$i}",
                'form_id' => $this->budgetForm->id,
                'user_id' => $this->user->id,
                'data' => json_encode($entry),
                'created_at' => Carbon::parse($entry['date']),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function test_user_can_generate_sum_aggregation_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'sum',
                'field' => 'amount',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['result', 'aggregation', 'field']);

        // Total: 1000 + 200 + 1500 + 300 = 3000
        $this->assertEquals(3000, $response->json('result'));
    }

    public function test_user_can_generate_average_aggregation_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'avg',
                'field' => 'amount',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        // Average: (1000 + 200 + 1500 + 300) / 4 = 750
        $this->assertEquals(750, $response->json('result'));
    }

    public function test_user_can_generate_min_max_aggregation_report(): void
    {
        $responseMin = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'min',
                'field' => 'amount',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $responseMin->assertStatus(Response::HTTP_OK);
        $this->assertEquals(200, $responseMin->json('result'));

        $responseMax = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'max',
                'field' => 'amount',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $responseMax->assertStatus(Response::HTTP_OK);
        $this->assertEquals(1500, $responseMax->json('result'));
    }

    public function test_user_can_generate_count_aggregation_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'count',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(4, $response->json('result'));
    }

    public function test_user_can_generate_time_series_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/time-series', [
                'form_id' => $this->budgetForm->id,
                'field' => 'amount',
                'aggregation' => 'sum',
                'period' => 'daily',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['data' => [['date', 'value']]]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_user_can_group_report_by_field(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/grouped', [
                'form_id' => $this->budgetForm->id,
                'group_by' => 'category',
                'field' => 'amount',
                'aggregation' => 'sum',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['groups' => [['category', 'value']]]);

        $groups = $response->json('groups');

        $incomeGroup = collect($groups)->firstWhere('category', 'income');
        $expenseGroup = collect($groups)->firstWhere('category', 'expense');

        $this->assertEquals(2500, $incomeGroup['value']); // 1000 + 1500
        $this->assertEquals(500, $expenseGroup['value']); // 200 + 300
    }

    public function test_user_can_export_report_as_csv(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/export', [
                'form_id' => $this->budgetForm->id,
                'format' => 'csv',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="report.csv"');

        $content = $response->getContent();
        $this->assertStringContainsString('amount', $content);
        $this->assertStringContainsString('category', $content);
    }

    public function test_user_can_export_report_as_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports/export', [
                'form_id' => $this->budgetForm->id,
                'format' => 'json',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['entries', 'meta']);
    }

    public function test_user_can_generate_weekly_summary_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/weekly-summary?form_id=' . $this->budgetForm->id);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'week_start',
                'week_end',
                'total_income',
                'total_expense',
                'net',
                'count',
            ]);
    }

    public function test_user_can_generate_monthly_summary_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/monthly-summary?form_id=' . $this->budgetForm->id . '&month=2025-01');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'month',
                'total_income',
                'total_expense',
                'net',
                'count',
            ]);
    }

    public function test_user_can_access_predefined_budget_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/predefined/budget?date_from=2025-01-01&date_to=2025-01-31');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'total_income',
                'total_expense',
                'balance',
                'savings_rate',
            ]);
    }

    public function test_user_can_access_predefined_medicine_report(): void
    {
        // Create medicine form
        $medicineForm = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Medicine Tracker',
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => 'field-medicine',
                'form_id' => $medicineForm->id,
                'name' => 'medicine_name',
                'label' => 'Medicine',
                'type' => 'text',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 'field-quantity',
                'form_id' => $medicineForm->id,
                'name' => 'quantity',
                'label' => 'Quantity',
                'type' => 'number',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/predefined/medicine?date_from=2025-01-01&date_to=2025-01-31');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'medicines',
                'total_consumption',
                'frequency',
            ]);
    }

    public function test_user_can_access_predefined_weight_tracking_report(): void
    {
        $weightForm = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Weight Tracker',
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => 'field-weight',
                'form_id' => $weightForm->id,
                'name' => 'weight',
                'label' => 'Weight',
                'type' => 'number',
                'unit' => 'kg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/predefined/weight?date_from=2025-01-01&date_to=2025-01-31');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'current_weight',
                'starting_weight',
                'change',
                'trend',
            ]);
    }

    public function test_user_can_filter_report_by_tags(): void
    {
        // Tag some entries
        $entry = DB::table('entries')->where('form_id', $this->budgetForm->id)->first();
        DB::table('entry_tags')->insert([
            ['entry_id' => $entry->id, 'tag' => 'recurring'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'sum',
                'field' => 'amount',
                'tags' => ['recurring'],
            ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_dashboard_shows_week_summary(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard/week');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'forms',
                'total_entries',
                'summary_by_form',
            ]);
    }

    public function test_dashboard_shows_month_summary(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard/month');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'forms',
                'total_entries',
                'summary_by_form',
            ]);
    }

    public function test_dashboard_shows_trends(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'weekly_trends',
                'monthly_trends',
            ]);
    }
}
