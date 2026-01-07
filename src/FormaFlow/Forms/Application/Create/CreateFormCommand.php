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
        private readonly bool $isQuiz = false,
        private readonly bool $singleSubmission = false,
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

    public function isQuiz(): bool
    {
        return $this->isQuiz;
    }

    public function isSingleSubmission(): bool
    {
        return $this->singleSubmission;
    }
}
