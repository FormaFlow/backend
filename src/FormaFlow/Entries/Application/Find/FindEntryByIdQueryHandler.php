<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Find;

use FormaFlow\Entries\Domain\EntryAggregate;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;

final readonly class FindEntryByIdQueryHandler
{
    public function __construct(
        private EntryRepository $repository,
    ) {
    }

    public function handle(FindEntryByIdQuery $query): ?EntryAggregate
    {
        return $this->repository->findById(new EntryId($query->id()));
    }
}
