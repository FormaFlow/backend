<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use Shared\Domain\DomainEvent;

final class EntryCreated extends DomainEvent
{
    public static function eventName(): string
    {
        return 'entry.created';
    }
}
