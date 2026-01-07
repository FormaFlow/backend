<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\UpdateField;

final readonly class UpdateFieldCommand
{
    public function __construct(
        public string $formId,
        public string $fieldId,
        public array $fieldData
    ) {
    }
}
