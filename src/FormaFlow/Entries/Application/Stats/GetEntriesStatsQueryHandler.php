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

        $baseDate = $query->date ? new DateTimeImmutable($query->date, $timezone) : new DateTimeImmutable('today', $timezone);

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
}
