<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\DomainEvent;

final class FormDeleted extends DomainEvent
{
    public function __construct(
        private readonly string $formId,
    ) {
    }

    public function formId(): string
    {
        return $this->formId;
    }
}
