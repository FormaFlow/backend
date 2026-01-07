<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Update;

use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use InvalidArgumentException;

final readonly class UpdateEntryCommandHandler
{
    public function __construct(
        private EntryRepository $entryRepository,
    ) {
    }

    public function handle(UpdateEntryCommand $command): void
    {
        $entry = $this->entryRepository->findById(new EntryId($command->id));

        if ($entry === null) {
            throw new InvalidArgumentException('Entry not found');
        }

        $entry->updateData($command->data);

        $this->entryRepository->save($entry);
    }
}
