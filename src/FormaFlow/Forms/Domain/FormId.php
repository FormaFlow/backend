<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\ValueObject;

final class FormId extends ValueObject
{
    public function __construct(
        private readonly string $id,
    ) {
        if (empty($id)) {
            throw new \InvalidArgumentException('FormId cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->id;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->id === $other->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
