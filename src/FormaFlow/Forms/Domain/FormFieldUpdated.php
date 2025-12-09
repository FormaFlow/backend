<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use DateTime;
use Shared\Domain\DomainEvent;

final class FormFieldUpdated extends DomainEvent
{
    public function __construct(
        private readonly string $formId,
        private readonly Field $field,
        DateTime $occurredOn = new DateTime(),
    ) {
        parent::__construct($this->formId, $occurredOn);
    }

    public static function eventName(): string
    {
        return 'form.field.updated';
    }

    public function formId(): string
    {
        return $this->formId;
    }

    public function field(): Field
    {
        return $this->field;
    }
}
