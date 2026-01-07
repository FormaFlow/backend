<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

final readonly class Field
{
    public function __construct(
        private string $id,
        private string $label,
        private FieldType $type,
        private bool $required = false,
        private ?array $options = null,
        private ?string $unit = null,
        private ?string $category = null,
        private int $order = 0,
        private ?string $correctAnswer = null,
        private int $points = 0,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): FieldType
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

    public function correctAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function points(): int
    {
        return $this->points;
    }
}
