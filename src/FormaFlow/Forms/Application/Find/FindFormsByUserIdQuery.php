<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

final readonly class FindFormsByUserIdQuery
{
    public function __construct(
        private string $userId,
        private int $limit = 15,
        private int $offset = 0,
        private ?bool $isQuiz = null,
    ) {
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function isQuiz(): ?bool
    {
        return $this->isQuiz;
    }
}
