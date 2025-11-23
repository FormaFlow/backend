<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use DateTime;
use FormaFlow\Forms\Domain\FormId;
use Shared\Domain\AggregateRoot;

final class EntryAggregate extends AggregateRoot
{
    public function __construct(
        private readonly EntryId $id,
        private readonly FormId $formId,
        private readonly string $userId,
        private array $data,
        private readonly DateTime $createdAt = new DateTime(),
    ) {
    }

    public function id(): EntryId
    {
        return $this->id;
    }

    public function formId(): FormId
    {
        return $this->formId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function updateData(array $data): void
    {
        $this->data = $data;
        $this->recordEvent(new EntryUpdated($this->id->value(), $this->formId->value()));
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public static function fromPrimitives(
        EntryId $id,
        FormId $formId,
        string $userId,
        array $data,
        DateTime $createdAt
    ): self {
        return new self($id, $formId, $userId, $data, $createdAt);
    }
}
