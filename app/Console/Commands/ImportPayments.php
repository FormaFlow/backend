<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use FormaFlow\Payments\Application\PaymentScheduleService;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentCategoryModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentOccurrenceModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentPlanModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ImportPayments extends Command
{
    protected $signature = 'payments:import {file} {--user=} {--source=initial-import}';
    protected $description = 'Import payment plans and occurrences from a private JSON file';

    public function handle(PaymentScheduleService $schedules): int
    {
        try {
            $payload = $this->readPayload($this->stringInput($this->argument('file'), 'file'));
            $user = $this->findUser($this->stringInput($this->option('user'), '--user'));
            $source = $this->stringInput($this->option('source'), '--source');
            $result = DB::transaction(fn(): array => $this->import($payload, $user, $source, $schedules));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Imported %d plans and %d occurrences for %s; generated %d future occurrences.',
            $result['plans'],
            $result['occurrences'],
            $user->email,
            $result['generated'],
        ));

        return self::SUCCESS;
    }

    /** @return array{plans: array<array-key, mixed>, ...} */
    private function readPayload(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("Import file is not readable: {$file}");
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Could not read import file: {$file}");
        }
        $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Import payload must be a JSON object.');
        }
        if (!isset($payload['plans']) || !is_array($payload['plans'])) {
            throw new RuntimeException('Import payload must contain a plans array.');
        }

        return $payload;
    }

    private function stringInput(mixed $value, string $name): string
    {
        if (!is_string($value)) {
            throw new RuntimeException("The {$name} value must be a string.");
        }

        return $value;
    }

    private function findUser(string $identifier): UserModel
    {
        if ($identifier === '') {
            throw new RuntimeException('The --user option is required and accepts a user UUID or email.');
        }

        $user = Str::isUuid($identifier)
            ? UserModel::query()->where('id', $identifier)->first()
            : UserModel::query()->where('email', $identifier)->first();
        if (!$user) {
            throw new RuntimeException("User not found: {$identifier}");
        }

        return $user;
    }

    private function import(
        array $payload,
        UserModel $user,
        string $source,
        PaymentScheduleService $schedules,
    ): array {
        $planCount = 0;
        $occurrenceCount = 0;
        $generated = 0;

        foreach ($payload['plans'] as $index => $item) {
            if (!is_array($item) || empty($item['source_key']) || empty($item['name'])) {
                throw new RuntimeException("Plan at index {$index} requires source_key and name.");
            }
            $category = null;
            if (!empty($item['category'])) {
                $category = PaymentCategoryModel::query()->firstOrCreate(
                    ['user_id' => $user->id, 'name' => (string)$item['category']],
                    ['color' => $item['category_color'] ?? null],
                );
            }

            $attributes = [
                'category_id' => $category?->id,
                'name' => (string)$item['name'],
                'payee' => $item['payee'] ?? null,
                'type' => $item['type'] ?? 'installment',
                'status' => $item['status'] ?? 'active',
                'currency' => strtoupper((string)($item['currency'] ?? 'RUB')),
                'schedule_type' => $item['schedule_type'] ?? 'manual',
                'starts_on' => $item['starts_on'] ?? null,
                'ends_on' => $item['ends_on'] ?? null,
                'day_of_month' => $item['day_of_month'] ?? null,
                'interval_days' => $item['interval_days'] ?? null,
                'total_installments' => $item['total_installments'] ?? null,
                'default_nominal_amount' => $item['default_nominal_amount'] ?? null,
                'default_expected_amount' => $item['default_expected_amount'] ?? null,
                'fee_percent' => $item['fee_percent'] ?? 0,
                'fee_fixed' => $item['fee_fixed'] ?? 0,
                'notes' => $item['notes'] ?? null,
                'closed_at' => $item['closed_at'] ?? null,
            ];
            $plan = PaymentPlanModel::query()->updateOrCreate(
                ['user_id' => $user->id, 'source_type' => $source, 'source_key' => (string)$item['source_key']],
                $attributes,
            );
            $planCount++;

            foreach (($item['occurrences'] ?? []) as $occurrenceIndex => $occurrenceData) {
                if (!is_array($occurrenceData) || empty($occurrenceData['due_on'])) {
                    throw new RuntimeException(
                        "Occurrence {$occurrenceIndex} of plan {$item['source_key']} requires due_on.",
                    );
                }
                $kind = $occurrenceData['kind'] ?? 'scheduled';
                $occurrence = PaymentOccurrenceModel::query()->firstOrNew([
                    'plan_id' => $plan->id,
                    'due_on' => $occurrenceData['due_on'],
                    'kind' => $kind,
                ]);
                $occurrence->fill([
                    'sequence_no' => $occurrenceData['sequence_no'] ?? null,
                    'total_count' => $occurrenceData['total_count'] ?? $plan->total_installments,
                    'nominal_amount' => $occurrenceData['nominal_amount'] ?? $plan->default_nominal_amount,
                    'expected_amount' => $occurrenceData['expected_amount'] ?? $schedules->expectedAmount($plan),
                    'actual_amount' => $occurrenceData['actual_amount'] ?? null,
                    'status' => $occurrenceData['status'] ?? 'planned',
                    'paid_at' => $occurrenceData['paid_at'] ?? null,
                    'source_type' => $source,
                    'source_key' => $occurrenceData['source_key']
                        ?? sprintf('%s:%s:%s', $item['source_key'], $occurrenceData['due_on'], $kind),
                    'notes' => $occurrenceData['notes'] ?? null,
                ]);
                $occurrence->save();
                $occurrenceCount++;
            }

            if (in_array($plan->schedule_type, ['monthly', 'interval'], true)) {
                $plan->refresh();
                $generated += $schedules->materialize($plan, CarbonImmutable::today()->addMonths(12));
            }
        }

        return ['plans' => $planCount, 'occurrences' => $occurrenceCount, 'generated' => $generated];
    }
}
