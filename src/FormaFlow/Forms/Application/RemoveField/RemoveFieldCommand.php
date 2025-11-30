<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\RemoveField;

final class RemoveFieldCommand
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
