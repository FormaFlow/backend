<?php

declare(strict_types=1);

namespace Shared\Domain;

use DateTime;

abstract class DomainEvent
{
    abstract public static function eventName(): string;

    public function __construct(
        private readonly string $aggregateId,
        private readonly DateTime $occurredOn = new DateTime(),
    ) {
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }
}
