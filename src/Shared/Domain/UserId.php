<?php

declare(strict_types=1);

namespace Shared\Domain;

use InvalidArgumentException;

final class UserId extends ValueObject
{
    public function __construct(private readonly string $id)
    {
        if (empty($id)) {
            throw new InvalidArgumentException('UserId cannot be empty');
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
