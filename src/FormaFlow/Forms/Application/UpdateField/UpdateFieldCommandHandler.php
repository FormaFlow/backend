<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\UpdateField;

use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final class UpdateFieldCommandHandler
{
    public function __construct(
        private readonly FormRepository $formRepository,
    ) {
    }

    public function handle(UpdateFieldCommand $command): void
    {
        $formId = new FormId($command->formId);
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            throw new InvalidArgumentException('Form not found');
        }

        if ($form->isPublished()) {
            throw new InvalidArgumentException('Cannot update field in published form');
        }

        $form->updateField($command->fieldId, $command->fieldData);
        $this->formRepository->save($form);
    }
}
