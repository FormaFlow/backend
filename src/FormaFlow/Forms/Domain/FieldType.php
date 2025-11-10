<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\ValueObject;

final class FieldType extends ValueObject
{
    private const VALID = ['text', 'number', 'date', 'boolean', 'select', 'currency', 'email'];

    public function __construct(
        private readonly string $type,
    ) {
        if (!in_array($type, self::VALID, true)) {
            throw new \InvalidArgumentException('Invalid field type');
        }
    }

    public function value(): string
    {
        return $this->type;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->type === $other->type;
    }

    public static function validTypes(): array
    {
        return self::VALID;
    }
}
