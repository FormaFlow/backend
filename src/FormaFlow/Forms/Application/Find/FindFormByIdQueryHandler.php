<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;

final readonly class FindFormByIdQueryHandler
{
    public function __construct(
        private FormRepository $repository,
    ) {
    }

    public function handle(FindFormByIdQuery $query): ?FormAggregate
    {
        $formId = new FormId($query->formId());
        return $this->repository->findById($formId);
    }
}
