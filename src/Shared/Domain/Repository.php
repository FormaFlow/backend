<?php

declare(strict_types=1);

namespace Shared\Domain;

interface Repository
{
    public function save(AggregateRoot $aggregate): void;
    public function delete(AggregateRoot $aggregate): void;
}
