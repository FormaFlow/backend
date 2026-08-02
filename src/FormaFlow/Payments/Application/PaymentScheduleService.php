<?php

declare(strict_types=1);

namespace FormaFlow\Payments\Application;

use Carbon\CarbonImmutable;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentOccurrenceModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentPlanModel;

final class PaymentScheduleService
{
    public function materialize(PaymentPlanModel $plan, CarbonImmutable $through): int
    {
        if ($plan->status !== 'active' || !in_array($plan->schedule_type, ['monthly', 'interval'], true)) {
            return 0;
        }

        $start = CarbonImmutable::parse($plan->starts_on)->startOfDay();
        $planEnd = $plan->ends_on ? CarbonImmutable::parse($plan->ends_on)->startOfDay() : null;
        $end = $planEnd !== null && $planEnd->lt($through) ? $planEnd : $through;
        $created = 0;
        $sequence = 1;
        $date = $this->dateForSequence($plan, $start, $sequence);

        while ($date->lte($end)) {
            if ($plan->total_installments !== null && $sequence > (int)$plan->total_installments) {
                break;
            }

            if ($date->gte($start)) {
                $occurrence = PaymentOccurrenceModel::query()->firstOrCreate(
                    ['plan_id' => $plan->id, 'due_on' => $date->toDateString(), 'kind' => 'scheduled'],
                    [
                        'sequence_no' => $plan->total_installments !== null ? $sequence : null,
                        'total_count' => $plan->total_installments,
                        'nominal_amount' => $plan->default_nominal_amount,
                        'expected_amount' => $this->expectedAmount($plan),
                        'status' => 'planned',
                        'source_type' => 'generated',
                        'source_key' => sprintf('%s:%s', $plan->id, $date->toDateString()),
                    ],
                );
                $created += $occurrence->wasRecentlyCreated ? 1 : 0;
            }

            $sequence++;
            $date = $this->dateForSequence($plan, $start, $sequence);
        }

        return $created;
    }

    public function expectedAmount(PaymentPlanModel $plan): ?string
    {
        if ($plan->default_expected_amount !== null) {
            return number_format((float)$plan->default_expected_amount, 2, '.', '');
        }

        if ($plan->default_nominal_amount === null) {
            return null;
        }

        $nominal = (float)$plan->default_nominal_amount;
        $value = $nominal + ($nominal * (float)$plan->fee_percent / 100) + (float)$plan->fee_fixed;

        return number_format($value, 2, '.', '');
    }

    private function dateForSequence(PaymentPlanModel $plan, CarbonImmutable $start, int $sequence): CarbonImmutable
    {
        if ($plan->schedule_type === 'interval') {
            return $start->addDays(((int)$plan->interval_days) * ($sequence - 1));
        }

        $month = $start->startOfMonth()->addMonths($sequence - 1);
        $day = min((int)($plan->day_of_month ?: $start->day), $month->daysInMonth);

        return $month->day($day);
    }
}
