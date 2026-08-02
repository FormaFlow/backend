<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use FormaFlow\Payments\Application\PaymentScheduleService;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentPlanModel;
use Illuminate\Console\Command;

final class MaterializePaymentSchedules extends Command
{
    protected $signature = 'payments:materialize {--months=12}';
    protected $description = 'Materialize active recurring payment schedules';

    public function handle(PaymentScheduleService $schedules): int
    {
        $monthsOption = $this->option('months');
        if (!is_numeric($monthsOption)) {
            $this->error('The --months option must be numeric.');
            return self::FAILURE;
        }
        $months = max(1, min(60, (int)$monthsOption));
        $through = CarbonImmutable::today()->addMonths($months);
        $created = 0;

        PaymentPlanModel::query()
            ->where('status', 'active')
            ->whereIn('schedule_type', ['monthly', 'interval'])
            ->orderBy('id')
            ->chunkById(100, function ($plans) use ($schedules, $through, &$created): void {
                foreach ($plans as $plan) {
                    $created += $schedules->materialize($plan, $through);
                }
            });

        $this->info("Created {$created} payment occurrences through {$through->toDateString()}.");

        return self::SUCCESS;
    }
}
