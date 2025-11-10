<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

use FormaFlow\Forms\Domain\FormRepository;

final class FindFormsByUserIdQueryHandler
{
    public function __construct(
        private readonly FormRepository $repository,
    ) {}

    /** @return array<string, mixed> */
    public function handle(FindFormsByUserIdQuery $query): array
    {
        $forms = $this->repository->findByUserId($query->userId());

        return [
            'forms' => $forms,
            'total' => count($forms),
            'limit' => $query->limit(),
            'offset' => $query->offset(),
        ];
    }
}
