<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence;

use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use Shared\Domain\AggregateRoot;

final class InMemoryFormRepository implements FormRepository
{
    /** @var FormAggregate[] */
    private array $forms = [];

    public function save(AggregateRoot $aggregate): void
    {
        $this->forms[$aggregate->id()->value()] = $aggregate;
    }

    public function delete(FormAggregate|AggregateRoot $aggregate): void
    {
        unset($this->forms[$aggregate->id()->value()]);
    }

    public function findById(FormId $id): ?FormAggregate
    {
        return $this->forms[$id->value()] ?? null;
    }

    /** @return FormAggregate[] */
    public function findByUserId(string $userId): array
    {
        return array_filter(
            $this->forms,
            static fn(FormAggregate $form): bool => $form->userId() === $userId
        );
    }
}
