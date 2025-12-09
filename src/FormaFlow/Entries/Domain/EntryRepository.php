<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use Shared\Domain\Repository;

interface EntryRepository extends Repository
{
    public function findById(EntryId $id): ?EntryAggregate;

    public function findByUserId(string $userId, array $filters = [], int $limit = 15, int $offset = 0): array;

    public function findByFormId(string $formId, int $limit = 15, int $offset = 0): array;

    /**
     * @return array<array{field: string, total_sum: float}>
     */
    public function getSumOfNumericFieldsByDateRange(
        \FormaFlow\Forms\Domain\FormId $formId,
        string $userId,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate = null
    ): array;
}
