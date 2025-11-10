<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\DomainEvent;

final class FormPublished extends DomainEvent
{
    public static function eventName(): string
    {
        return 'form.published';
    }
}
