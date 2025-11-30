<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\DomainEvent;

final class FormDeleted extends DomainEvent
{
    public static function eventName(): string
    {
        return 'delete';
    }

    public function formId(): string
    {
        return $this->aggregateId();
    }
}
