<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Create;

final class CreateEntryCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $formId,
        public readonly string $userId,
        public readonly array $data,
        public readonly ?int $duration = null,
    ) {
    }
}
