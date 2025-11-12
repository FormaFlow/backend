<?php

declare(strict_types=1);

namespace Shared\Domain;

abstract class ValueObject
{
    abstract public function value(): mixed;

    abstract public function equals(self $other): bool;
}
