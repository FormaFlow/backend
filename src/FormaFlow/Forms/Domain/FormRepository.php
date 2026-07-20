<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use Shared\Domain\Repository;

interface FormRepository extends Repository
{
    public function findById(FormId $id): ?FormAggregate;

    /** @return FormAggregate[] */
    public function findByUserId(string $userId, ?bool $isQuiz = null): array;

    /** @return array{0: FormSummary[], 1: int} */
    public function findSummariesByUserId(
        string $userId,
        ?bool $isQuiz,
        ?string $search,
        int $limit,
        int $offset,
    ): array;
}
