<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\Repository;

interface FormRepository extends Repository
{
    public function findById(FormId $id): ?FormAggregate;

    /** @return FormAggregate[] */
    public function findByUserId(string $userId): array;

    public function delete(FormId $id): void;
}
