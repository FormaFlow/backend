<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use InvalidArgumentException;
use Shared\Domain\ValueObject;

final class FormName extends ValueObject
{
    public function __construct(
        private readonly string $name,
    ) {
        if (strlen($name) < 3 || strlen($name) > 255) {
            throw new InvalidArgumentException('Invalid form name');
        }
    }

    public function value(): string
    {
        return $this->name;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->name === $other->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
