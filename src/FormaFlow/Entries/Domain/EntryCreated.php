<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use Shared\Domain\DomainEvent;

final class EntryCreated extends DomainEvent
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $formId,
        public readonly string $userId
    ) {
    }

    public static function eventName(): string
    {
        return 'entry.created';
    }
}
