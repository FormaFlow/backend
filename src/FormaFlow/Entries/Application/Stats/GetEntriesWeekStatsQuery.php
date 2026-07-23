<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Stats;

final readonly class GetEntriesWeekStatsQuery
{
    public function __construct(
        public string $formId,
        public string $userId,
        public ?string $date = null,
    ) {
    }
}
