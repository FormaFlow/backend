<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Update;

use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Domain\FormRepository;
use RuntimeException;

final readonly class UpdateFormCommandHandler
{
    public function __construct(
        private FormRepository $formRepository
    ) {
    }

    public function handle(UpdateFormCommand $command): void
    {
        $formId = new FormId($command->id);
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            throw new RuntimeException('Not found');
        }

        if ($form->userId() !== $command->userId) {
            throw new RuntimeException('Forbidden');
        }

        $updatedForm = FormAggregate::fromPrimitives(
            id: $formId,
            userId: $form->userId(),
            name: $command->name !== null ? new FormName($command->name) : $form->name(),
            description: $command->description ?? $form->description(),
            published: $form->isPublished(),
            version: $form->isPublished() ? $form->getVersion() + 1 : $form->getVersion(),
            createdAt: $form->createdAt(),
            fields: $form->fields(),
            isQuiz: $command->isQuiz ?? $form->isQuiz(),
            singleSubmission: $command->singleSubmission ?? $form->isSingleSubmission(),
        );

        $this->formRepository->save($updatedForm);
    }
}
