<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Update;

final readonly class UpdateEntryCommand
{
    public function __construct(
        public string $id,
        public array $data,
    ) {
    }
}
