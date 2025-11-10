<?php

declare(strict_types=1);

namespace Shared\Domain;

abstract class AggregateRoot
{
    private array $domainEvents = [];

    public function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
