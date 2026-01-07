<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Delete;

final readonly class DeleteFormCommand
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
