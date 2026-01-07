<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Stats;

final readonly class GetEntriesStatsQueryResult
{
    /**
     * @param array<array{field: string, sum_today: float, sum_month: float}> $stats
     */
    public function __construct(
        public array $stats,
    ) {
    }
}
