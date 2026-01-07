<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Stats;

use DateTimeImmutable;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;

final readonly class GetEntriesStatsQueryHandler
{
    public function __construct(
        private EntryRepository $entryRepository,
        private FormRepository $formRepository
    ) {
    }

    public function handle(GetEntriesStatsQuery $query): GetEntriesStatsQueryResult
    {
        $form = $this->formRepository->findById(new FormId($query->formId));
        if (null === $form) {
            return new GetEntriesStatsQueryResult([]);
        }

        $numericFields = [];
        foreach ($form->fields() as $field) {
            if (in_array($field->type()->value(), ['number', 'currency'])) {
                $numericFields[] = $field->id();
            }
        }

        if (empty($numericFields)) {
            return new GetEntriesStatsQueryResult([]);
        }

        $todayStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
            new FormId($query->formId),
            $query->userId,
            new DateTimeImmutable('today')
        );

        $monthStatsRaw = $this->entryRepository->getSumOfNumericFieldsByDateRange(
            new FormId($query->formId),
            $query->userId,
            new DateTimeImmutable('first day of this month'),
            new DateTimeImmutable('last day of this month')
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
