<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\RemoveField;

final readonly class RemoveFieldCommand
{
    public function __construct(
        private string $formId,
        private string $fieldId,
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
