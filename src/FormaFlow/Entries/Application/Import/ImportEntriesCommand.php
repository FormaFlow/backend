<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Import;

final readonly class ImportEntriesCommand
{
    public function __construct(
        public string $userId,
        public string $formId,
        public string $csvData,
        public string $delimiter = ',',
    ) {
    }
}
