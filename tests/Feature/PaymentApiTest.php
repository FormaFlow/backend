<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentOccurrenceModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentPlanModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PaymentApiTest extends TestCase
{
    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-02 12:00:00 Europe/Moscow');
        $user = UserModel::factory()->create(['email' => 'payments@example.com']);
        self::assertInstanceOf(UserModel::class, $user);
        $this->user = $user;
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_create_monthly_plan_and_view_overview(): void
    {
        $category = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/categories', ['name' => 'Жильё', 'color' => '#16a34a'])
            ->assertStatus(Response::HTTP_CREATED)
            ->json();

        $plan = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/plans', [
                'category_id' => $category['id'],
                'name' => 'Аренда',
                'payee' => 'Арендодатель',
                'type' => 'recurring',
                'currency' => 'RUB',
                'schedule_type' => 'monthly',
                'starts_on' => '2026-08-16',
                'day_of_month' => 16,
                'default_nominal_amount' => '22000.00',
                'default_expected_amount' => '22000.00',
            ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('name', 'Аренда')
            ->json();

        self::assertSame(12, PaymentOccurrenceModel::query()->where('plan_id', $plan['id'])->count());

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payments/overview?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('summary.overdue_count', 0)
            ->assertJsonPath('summary.due_soon_count', 0)
            ->assertJsonPath('summary.expected_this_month', '22000.00')
            ->assertJsonCount(1, 'occurrences');
    }

    public function test_payment_can_be_paid_early_reopened_and_plan_closed(): void
    {
        $plan = PaymentPlanModel::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Кредит',
            'type' => 'installment',
            'status' => 'active',
            'currency' => 'RUB',
            'schedule_type' => 'manual',
        ]);
        $first = PaymentOccurrenceModel::query()->create([
            'plan_id' => $plan->id,
            'due_on' => '2026-08-15',
            'kind' => 'scheduled',
            'expected_amount' => '9000.00',
            'status' => 'planned',
        ]);
        $second = PaymentOccurrenceModel::query()->create([
            'plan_id' => $plan->id,
            'due_on' => '2026-08-29',
            'kind' => 'scheduled',
            'expected_amount' => '9000.00',
            'status' => 'planned',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/payments/occurrences/{$first->id}/pay", [
                'paid_at' => '2026-08-02T12:00:00+03:00',
                'actual_amount' => '9050.25',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('actual_amount', '9050.25');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/payments/occurrences/{$first->id}/reopen")
            ->assertOk()
            ->assertJsonPath('status', 'planned')
            ->assertJsonPath('actual_amount', null);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/payments/plans/{$plan->id}/close", [
                'paid_at' => '2026-08-03T09:00:00+03:00',
                'actual_amount' => '17000.00',
                'notes' => 'Досрочное погашение',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $first->refresh();
        $second->refresh();
        self::assertSame('cancelled', $first->status);
        self::assertSame('cancelled', $second->status);
        $this->assertDatabaseHas('payment_occurrences', [
            'plan_id' => $plan->id,
            'kind' => 'settlement',
            'status' => 'paid',
            'actual_amount' => '17000.00',
        ]);
    }

    public function test_payment_data_is_isolated_by_user(): void
    {
        $other = UserModel::factory()->create(['email' => 'other-payments@example.com']);
        $plan = PaymentPlanModel::query()->create([
            'user_id' => $other->id,
            'name' => 'Чужой платёж',
            'type' => 'one_off',
            'status' => 'active',
            'currency' => 'RUB',
            'schedule_type' => 'one_off',
            'starts_on' => '2026-08-10',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/payments/plans/{$plan->id}")
            ->assertNotFound();
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payments/plans')
            ->assertJsonCount(0, 'plans');
    }

    public function test_private_json_import_is_idempotent(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'payments-import-');
        self::assertIsString($file);
        file_put_contents($file, json_encode([
            'plans' => [[
                'source_key' => 'test-loan',
                'category' => 'Кредиты',
                'name' => 'Тестовый кредит',
                'type' => 'installment',
                'schedule_type' => 'manual',
                'occurrences' => [[
                    'due_on' => '2026-08-15',
                    'nominal_amount' => '7774.73',
                    'expected_amount' => '9000.00',
                    'actual_amount' => '9000.00',
                    'status' => 'paid',
                    'paid_at' => '2026-08-01T10:00:00+03:00',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));

        try {
            self::assertSame(0, Artisan::call('payments:import', [
                'file' => $file,
                '--user' => $this->user->email,
            ]), Artisan::output());
            self::assertSame(0, Artisan::call('payments:import', [
                'file' => $file,
                '--user' => $this->user->email,
            ]), Artisan::output());
        } finally {
            @unlink($file);
        }

        $plan = PaymentPlanModel::query()->where('user_id', $this->user->id)->sole();

        self::assertSame(
            1,
            PaymentOccurrenceModel::query()->where('plan_id', $plan->id)->count(),
        );
    }

    public function test_payment_materializer_is_registered_in_scheduler(): void
    {
        $exitCode = Artisan::call('schedule:list');
        $output = Artisan::output();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('payments:materialize', $output);
    }
}
