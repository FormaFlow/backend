<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Import;

final readonly class ImportFormFromJsonCommand
{
    public function __construct(
        private array $data,
        private string $userId // We still need a user to assign the form to
    )
    {
    }

    public function data(): array
    {
        return $this->data;
    }

    public function userId(): string
    {
        return $this->userId;
    }
}
