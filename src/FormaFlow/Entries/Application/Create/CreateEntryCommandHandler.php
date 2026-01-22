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

final readonly class CreateEntryCommandHandler
{
    public function __construct(
        private EntryRepository $entryRepository,
        private FormRepository $formRepository,
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

        if ($form->isSingleSubmission()) {
            [, $total] = $this->entryRepository->findByUserId(
                $command->userId,
                ['form_id' => $command->formId],
            );
            if ($total > 0) {
                throw new InvalidArgumentException('You have already submitted this form.');
            }
        }

        $score = null;
        if ($form->isQuiz()) {
            $score = 0;
            foreach ($form->fields() as $field) {
                $submittedValue = $command->data[$field->id()] ?? null;
                $correctAnswer = $field->correctAnswer();

                if ($submittedValue !== null && $correctAnswer !== null) {
                    $v1 = is_string($submittedValue) ? trim($submittedValue) : $submittedValue;
                    $v2 = is_string($correctAnswer) ? trim($correctAnswer) : $correctAnswer;

                    if (is_string($v1) && is_string($v2)) {
                        if (mb_strtolower($v1) === mb_strtolower($v2)) {
                            $score += $field->points();
                        }
                    } elseif ($v1 === $v2) {
                        $score += $field->points();
                    }
                }
            }
        }

        $entry = new EntryAggregate(
            id: new EntryId($command->id),
            formId: new FormId($command->formId),
            userId: $command->userId,
            data: $command->data,
            score: $score,
            duration: $command->duration,
        );

        $entry->recordEvent(new EntryCreated($command->id));

        $this->entryRepository->save($entry);
    }
}
