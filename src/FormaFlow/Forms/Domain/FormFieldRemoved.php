<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\DomainEvent;

final class FormFieldRemoved extends DomainEvent
{
    public static function eventName(): string
    {
        return 'delete';
    }

    public function fieldId(): string
    {
        return $this->aggregateId();
    }
}
