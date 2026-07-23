<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Stats;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Users\Domain\UserRepository;
use Shared\Domain\UserId;

final readonly class GetEntriesStatsQueryHandler
{
    public function __construct(
        private EntryRepository $entryRepository,
        private FormRepository $formRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws DateInvalidTimeZoneException
     */
    public function handle(GetEntriesStatsQuery $query): GetEntriesStatsQueryResult
    {
        $form = $this->formRepository->findById(new FormId($query->formId));
        if (null === $form) {
            return new GetEntriesStatsQueryResult([]);
        }

        $user = $this->userRepository->findById(new UserId($query->userId));
        $timezoneStr = $user?->timezone() ?? 'Europe/Moscow';
        $timezone = new DateTimeZone($timezoneStr);
        $utc = new DateTimeZone('UTC');

        $numericFields = [];
        foreach ($form->fields() as $field) {
            if (in_array($field->type()->value(), ['number', 'currency'])) {
                $numericFields[] = $field->id();
            }
        }

        $baseDate = $query->date
            ? new DateTimeImmutable($query->date, $timezone)
            : new DateTimeImmutable('today', $timezone);

        $todayStart = $baseDate->setTime(0, 0, 0);
        $todayEnd = $baseDate->setTime(23, 59, 59);

        $monthStart = $baseDate->modify('first day of this month 00:00:00');
        $monthEnd = $baseDate->modify('last day of this month 23:59:59');

        $stats = [];

        $todayCount = $this->entryRepository->countEntriesByDateRange(
            new FormId($query->formId),
            $query->userId,
            $todayStart->setTimezone($utc),
            $todayEnd->setTimezone($utc)
        );

        $monthCount = $this->entryRepository->countEntriesByDateRange(
            new FormId($query->formId),
            $query->userId,
            $monthStart->setTimezone($utc),
            $monthEnd->setTimezone($utc)
        );

        $stats[] = [
            'field' => '_count',
            'sum_today' => (float)$todayCount,
            'sum_month' => (float)$monthCount,
        ];

        if (!empty($numericFields)) {
            $todayStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
                new FormId($query->formId),
                $query->userId,
                $todayStart->setTimezone($utc),
                $todayEnd->setTimezone($utc)
            );

            $monthStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
                new FormId($query->formId),
                $query->userId,
                $monthStart->setTimezone($utc),
                $monthEnd->setTimezone($utc)
            );

            foreach ($numericFields as $field) {
                $todaySum = 0.0;
                foreach ($todayStatsRaw as $stat) {
                    if ($stat['field'] === $field) {
                        $todaySum = $stat['total_sum'];
                        break;
                    }
                }

                $monthSum = 0.0;
                foreach ($monthStatsRaw as $stat) {
                    if ($stat['field'] === $field) {
                        $monthSum = $stat['total_sum'];
                        break;
                    }
                }

                $stats[] = [
                    'field' => $field,
                    'sum_today' => $todaySum,
                    'sum_month' => $monthSum,
                ];
            }
        }

        return new GetEntriesStatsQueryResult($stats);
    }

    /**
     * @return array{
     *     days: array<int, array{date: string, stats: array<int, array{field: string, sum: float}>}>,
     *     months: array<string, array<int, array{field: string, sum_month: float}>>
     * }
     * @throws DateMalformedStringException
     * @throws DateInvalidTimeZoneException
     */
    public function handleWeek(GetEntriesWeekStatsQuery $query): array
    {
        $form = $this->formRepository->findById(new FormId($query->formId));
        if ($form === null) {
            return ['days' => [], 'months' => []];
        }

        $user = $this->userRepository->findById(new UserId($query->userId));
        $timezoneStr = $user?->timezone() ?? 'Europe/Moscow';
        $timezone = new DateTimeZone($timezoneStr);
        $utc = new DateTimeZone('UTC');
        $baseDate = $query->date
            ? new DateTimeImmutable($query->date, $timezone)
            : new DateTimeImmutable('today', $timezone);
        $endDate = $baseDate->setTime(23, 59, 59);
        $startDate = $baseDate->modify('-6 days')->setTime(0, 0, 0);

        $numericFields = [];
        foreach ($form->fields() as $field) {
            if (in_array($field->type()->value(), ['number', 'currency'], true)) {
                $numericFields[] = $field->id();
            }
        }

        $dailyTotals = $this->entryRepository->getStatsByDay(
            new FormId($query->formId),
            $query->userId,
            $startDate->setTimezone($utc),
            $endDate->setTimezone($utc),
            $numericFields,
            $timezoneStr,
        );

        $days = [];
        $months = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $baseDate->modify("-{$offset} days")->setTime(0, 0, 0);
            $date = $day->format('Y-m-d');
            $totals = $dailyTotals[$date] ?? ['count' => 0, 'sums' => []];
            $stats = [['field' => '_count', 'sum' => (float)$totals['count']]];
            foreach ($numericFields as $fieldId) {
                $stats[] = [
                    'field' => $fieldId,
                    'sum' => (float)($totals['sums'][$fieldId] ?? 0),
                ];
            }
            $days[] = ['date' => $date, 'stats' => $stats];

            $monthKey = $day->format('Y-m');
            if (!isset($months[$monthKey])) {
                $monthStats = $this->handle(new GetEntriesStatsQuery(
                    formId: $query->formId,
                    userId: $query->userId,
                    date: $date,
                ));
                $months[$monthKey] = array_map(static fn(array $stat): array => [
                    'field' => $stat['field'],
                    'sum_month' => $stat['sum_month'],
                ], $monthStats->stats);
            }
        }

        return ['days' => $days, 'months' => $months];
    }
}
