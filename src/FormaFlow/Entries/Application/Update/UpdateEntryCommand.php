<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Update;

final class UpdateEntryCommand
{
    public function __construct(
        public readonly string $id,
        public readonly array $data,
    ) {
    }
}
