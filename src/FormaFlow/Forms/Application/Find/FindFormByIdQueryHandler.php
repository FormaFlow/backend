<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;

final class FindFormByIdQueryHandler
{
    public function __construct(
        private readonly FormRepository $repository,
    ) {}

    public function handle(FindFormByIdQuery $query)
    {
        $formId = new FormId($query->formId());
        return $this->repository->findById($formId);
    }
}
