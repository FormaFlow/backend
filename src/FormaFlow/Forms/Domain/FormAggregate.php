<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use DateTime;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;

final class FormAggregate extends AggregateRoot
{
    /** @var Field[] */
    private array $fields = [];
    private bool $published = false;
    private int $version = 1;

    public function __construct(
        private readonly FormId $id,
        private readonly string $userId,
        private readonly FormName $name,
        private readonly ?string $description = null,
        private readonly DateTime $createdAt = new DateTime(),
    ) {
    }

    public function id(): FormId
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function name(): FormName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /** @return Field[] */
    public function fields(): array
    {
        return $this->fields;
    }

    public function addField(Field $field): void
    {
        $this->fields[] = $field;
        $this->recordEvent(new FormFieldAdded($this->id->value(), $field));
    }

    public function removeField(string $fieldId): void
    {
        $fieldIndex = null;
        foreach ($this->fields as $index => $field) {
            if ($field->id() === $fieldId) {
                $fieldIndex = $index;
                break;
            }
        }

        if ($fieldIndex === null) {
            throw new InvalidArgumentException('Field not found');
        }

        array_splice($this->fields, $fieldIndex, 1);
        $this->recordEvent(new FormFieldRemoved($this->id->value()));
    }

    public function updateField(string $fieldId, array $fieldData): void
    {
        $fieldIndex = null;
        foreach ($this->fields as $index => $field) {
            if ($field->id() === $fieldId) {
                $fieldIndex = $index;
                break;
            }
        }

        if ($fieldIndex === null) {
            throw new InvalidArgumentException('Field not found');
        }

        $existingField = $this->fields[$fieldIndex];

        $updatedField = new Field(
            id: $existingField->id(),
            label: $fieldData['label'] ?? $existingField->label(),
            type: isset($fieldData['type']) ? new FieldType($fieldData['type']) : $existingField->type(),
            required: $fieldData['required'] ?? $existingField->isRequired(),
            options: $fieldData['options'] ?? $existingField->options(),
            unit: $fieldData['unit'] ?? $existingField->unit(),
            category: $fieldData['category'] ?? $existingField->category(),
            order: $fieldData['order'] ?? $existingField->order(),
        );

        $this->fields[$fieldIndex] = $updatedField;
        $this->recordEvent(new FormFieldUpdated($this->id->value(), $updatedField));
    }

    public function publish(): void
    {
        if ($this->published) {
            throw new InvalidArgumentException('Form is already published');
        }

        if (empty($this->fields)) {
            throw new InvalidArgumentException('Cannot publish form without fields');
        }

        $this->published = true;
        $this->recordEvent(new FormPublished($this->id->value()));
    }

    public function incrementVersion(): void
    {
        $this->version++;
    }

    public static function fromPrimitives(
        FormId $id,
        string $userId,
        FormName $name,
        ?string $description,
        bool $published,
        int $version,
        DateTime $createdAt,
        array $fields
    ): self {
        $self = new self(
            id: $id,
            userId: $userId,
            name: $name,
            description: $description,
            createdAt: $createdAt,
        );

        $self->published = $published;
        $self->version = $version;
        $self->fields = $fields;

        return $self;
    }
}
