<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Create;

use FormaFlow\Entries\Domain\EntryAggregate;
use FormaFlow\Entries\Domain\EntryCreated;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final class CreateEntryCommandHandler
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly FormRepository $formRepository,
    ) {
    }

    public function handle(CreateEntryCommand $command): void
    {
        $form = $this->formRepository->findById(new FormId($command->formId));

        if ($form === null) {
            throw new InvalidArgumentException('Form not found');
        }

        if (!$form->isPublished()) {
            throw new InvalidArgumentException('Cannot create entry from unpublished form');
        }

        $entry = new EntryAggregate(
            id: new EntryId($command->id),
            formId: new FormId($command->formId),
            userId: $command->userId,
            data: $command->data,
        );

        $entry->recordEvent(
            new EntryCreated(
                $command->id,
                $command->formId,
                $command->userId
            )
        );

        $this->entryRepository->save($entry);
    }
}
