<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Find;

final readonly class FindEntryByIdQuery
{
    public function __construct(
        private string $id,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }
}
