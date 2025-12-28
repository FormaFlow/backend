<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Domain;

use DateTimeImmutable;
use FormaFlow\Forms\Domain\FormId;
use Shared\Domain\Repository;

interface EntryRepository extends Repository
{
    public function findById(EntryId $id): ?EntryAggregate;

    /**
     * @return array{0: EntryAggregate[], 1: int}
     */
    public function findByUserId(string $userId, array $filters = [], int $limit = 15, int $offset = 0): array;

    public function findByFormId(string $formId, int $limit = 15, int $offset = 0): array;

    /**
     * @return array<array{field: string, total_sum: float}>
     */
    public function getSumOfNumericFieldsByDateRange(
        FormId $formId,
        string $userId,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate = null
    ): array;
}
