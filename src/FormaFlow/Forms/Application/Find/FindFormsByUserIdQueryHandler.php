<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

use FormaFlow\Forms\Domain\FormRepository;

final readonly class FindFormsByUserIdQueryHandler
{
    public function __construct(
        private FormRepository $repository,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(FindFormsByUserIdQuery $query): array
    {
        [$forms, $total] = $this->repository->findSummariesByUserId(
            userId: $query->userId(),
            isQuiz: $query->isQuiz(),
            search: $query->search(),
            limit: $query->limit(),
            offset: $query->offset(),
        );

        return [
            'forms' => $forms,
            'total' => $total,
            'limit' => $query->limit(),
            'offset' => $query->offset(),
        ];
    }
}
