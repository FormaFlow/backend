<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\DomainEvent;

final class FormFieldRemoved extends DomainEvent
{
    public function __construct(
        private readonly string $formId,
        private readonly string $fieldId,
    ) {
    }

    public function formId(): string
    {
        return $this->formId;
    }

    public function fieldId(): string
    {
        return $this->fieldId;
    }
}
