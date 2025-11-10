<?php

declare(strict_types=1);

namespace Shared\Infrastructure;

use Ramsey\Uuid\Uuid as RamseyUuid;

final class Uuid
{
    public static function generate(): string
    {
        return RamseyUuid::uuid4()->toString();
    }
}
