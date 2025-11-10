<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

final class FindFormsByUserIdQuery
{
    public function __construct(
        private readonly string $userId,
        private readonly int $limit = 15,
        private readonly int $offset = 0,
    ) {}

    public function userId(): string { return $this->userId; }
    public function limit(): int { return $this->limit; }
    public function offset(): int { return $this->offset; }
}
