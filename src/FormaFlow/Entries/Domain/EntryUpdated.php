<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use Shared\Domain\DomainEvent;

final class EntryUpdated extends DomainEvent
{
    public static function eventName(): string
    {
        return 'entry.updated';
    }
}
