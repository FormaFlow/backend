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

        $numericFields = [];
        foreach ($form->fields() as $field) {
            if (in_array($field->type()->value(), ['number', 'currency'])) {
                $numericFields[] = $field->id();
            }
        }

        if (empty($numericFields)) {
            return new GetEntriesStatsQueryResult([]);
        }

        $utc = new DateTimeZone('UTC');

        $todayStart = new DateTimeImmutable('today', $timezone);
        $todayEnd = (new DateTimeImmutable('tomorrow', $timezone))->modify('-1 second');

        $todayStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
            new FormId($query->formId),
            $query->userId,
            $todayStart->setTimezone($utc),
            $todayEnd->setTimezone($utc)
        );

        $monthStart = new DateTimeImmutable('first day of this month 00:00:00', $timezone);
        $monthEnd = new DateTimeImmutable('last day of this month 23:59:59', $timezone);

        $monthStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
            new FormId($query->formId),
            $query->userId,
            $monthStart->setTimezone($utc),
            $monthEnd->setTimezone($utc)
        );

        $stats = [];
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

        return new GetEntriesStatsQueryResult($stats);
    }
}
