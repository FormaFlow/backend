<?php

declare(strict_types=1);

namespace Shared\Domain;

use InvalidArgumentException;

final class UserName extends ValueObject
{
    public function __construct(private readonly string $name)
    {
        if (strlen($name) < 2 || strlen($name) > 255) {
            throw new InvalidArgumentException('Invalid user name');
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
}
