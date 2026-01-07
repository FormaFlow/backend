<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Create;

final readonly class CreateFormCommand
{
    public function __construct(
        private string $id,
        private string $userId,
        private string $name,
        private ?string $description = null,
        private bool $isQuiz = false,
        private bool $singleSubmission = false,
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
