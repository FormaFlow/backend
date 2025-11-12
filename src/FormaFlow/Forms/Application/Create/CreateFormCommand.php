<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Create;

final class CreateFormCommand
{
    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly string $name,
        private readonly ?string $description = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
