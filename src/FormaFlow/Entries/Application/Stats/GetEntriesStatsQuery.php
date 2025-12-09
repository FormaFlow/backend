<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Stats;

final class GetEntriesStatsQuery
{
    public function __construct(
        public readonly string $formId,
        public readonly string $userId,
    ) {
    }
}
