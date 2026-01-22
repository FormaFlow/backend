<?php

declare(strict_types=1);

namespace Tests\Integration\Reporting;

use Carbon\Carbon;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ReportGenerationIntegrationTest extends TestCase
{

    protected UserModel $user;
    protected FormModel $budgetForm;
    protected string $amountId = '00000000-0000-0000-0000-000000000110';
    protected string $categoryId = '00000000-0000-0000-0000-000000000112';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create();
        $this->budgetForm = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Budget Tracker',
        ]);

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
                'id' => $this->categoryId,
                'form_id' => $this->budgetForm->id,
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
            [$this->amountId => 1000, $this->categoryId => 'income', 'date' => '2025-01-05'],
            [$this->amountId => 200, $this->categoryId => 'expense', 'date' => '2025-01-10'],
            [$this->amountId => 1500, $this->categoryId => 'income', 'date' => '2025-01-15'],
            [$this->amountId => 300, $this->categoryId => 'expense', 'date' => '2025-01-20'],
            [$this->amountId => 2000, $this->categoryId => 'income', 'date' => '2025-02-05'],
            [$this->amountId => 500, $this->categoryId => 'expense', 'date' => '2025-02-10'],
        ];

        foreach ($entries as $i => $entry) {
            $data = $entry;
            unset($data['date']);

            DB::table('entries')->insert([
                'id' => "00000000-0000-0000-0000-00000000090{$i}",
                'form_id' => $this->budgetForm->id,
                'user_id' => $this->user->id,
                'data' => json_encode($data),
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
                'field' => $this->amountId,
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['result', 'aggregation', 'field']);

        $this->assertEquals(3000, $response->json('result'));
    }

    public function test_user_can_generate_average_aggregation_report(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'avg',
                'field' => $this->amountId,
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals(750, $response->json('result'));
    }

    public function test_user_can_generate_min_max_aggregation_report(): void
    {
        $responseMin = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'min',
                'field' => $this->amountId,
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $responseMin->assertStatus(Response::HTTP_OK);
        $this->assertEquals(200, $responseMin->json('result'));

        $responseMax = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'max',
                'field' => $this->amountId,
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
                'field' => $this->amountId,
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
                'group_by' => $this->categoryId,
                'field' => $this->amountId,
                'aggregation' => 'sum',
                'date_from' => '2025-01-01',
                'date_to' => '2025-01-31',
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['groups' => [['category', 'value']]]);

        $groups = $response->json('groups');

        $incomeGroup = collect($groups)->firstWhere('category', 'income');
        $expenseGroup = collect($groups)->firstWhere('category', 'expense');

        $this->assertEquals(2500, $incomeGroup['value']);
        $this->assertEquals(500, $expenseGroup['value']);
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
        $this->assertStringContainsString($this->amountId, $content);
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
        $this->markTestSkipped('Legacy summary endpoints need update to support field IDs.');
    }

    public function test_user_can_generate_monthly_summary_report(): void
    {
        $this->markTestSkipped('Legacy summary endpoints need update to support field IDs.');
    }

    public function test_user_can_access_predefined_budget_report(): void
    {
        $this->markTestSkipped('Legacy summary endpoints need update to support field IDs.');
    }

    public function test_user_can_access_predefined_medicine_report(): void
    {
        $this->markTestSkipped('Legacy summary endpoints need update to support field IDs.');
    }

    public function test_user_can_access_predefined_weight_tracking_report(): void
    {
        $this->markTestSkipped('Legacy summary endpoints need update to support field IDs.');
    }

    public function test_user_can_filter_report_by_tags(): void
    {
        $entry = DB::table('entries')->where('form_id', $this->budgetForm->id)->first();
        DB::table('entry_tags')->insert([
            ['entry_id' => $entry->id, 'tag' => 'recurring'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'form_id' => $this->budgetForm->id,
                'aggregation' => 'sum',
                'field' => $this->amountId,
                'tags' => ['recurring'],
            ]);

        $response->assertStatus(Response::HTTP_OK);
    }
}
