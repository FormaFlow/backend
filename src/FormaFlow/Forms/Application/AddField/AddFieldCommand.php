<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\AddField;

final class AddFieldCommand
{
    public function __construct(
        private readonly string $formId,
        private readonly string $fieldId,
        private readonly string $label,
        private readonly string $type,
        private readonly bool $required = false,
        private readonly ?array $options = null,
        private readonly ?string $unit = null,
        private readonly ?string $category = null,
        private readonly int $order = 0,
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

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function options(): ?array
    {
        return $this->options;
    }

    public function unit(): ?string
    {
        return $this->unit;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function order(): int
    {
        return $this->order;
    }
}
