<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Delete;

final class DeleteFormCommand
{
    public function __construct(
        private readonly string $id,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }
}
