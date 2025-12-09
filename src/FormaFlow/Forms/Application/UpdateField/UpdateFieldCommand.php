<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\UpdateField;

final class UpdateFieldCommand
{
    public function __construct(
        public readonly string $formId,
        public readonly string $fieldId,
        public readonly array $fieldData
    ) {
    }
}
