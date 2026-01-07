<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Create;

final readonly class CreateEntryCommand
{
    public function __construct(
        public string $id,
        public string $formId,
        public string $userId,
        public array $data,
        public ?int $duration = null,
    ) {
    }
}
