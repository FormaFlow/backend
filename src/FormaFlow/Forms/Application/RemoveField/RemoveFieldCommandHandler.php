<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\RemoveField;

use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final readonly class RemoveFieldCommandHandler
{
    public function __construct(
        private FormRepository $formRepository,
    ) {
    }

    public function handle(RemoveFieldCommand $command): void
    {
        $formId = new FormId($command->formId());
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            throw new InvalidArgumentException('Form not found');
        }

        if ($form->isPublished()) {
            throw new InvalidArgumentException('Cannot remove field from published form');
        }

        $form->removeField($command->fieldId());
        $this->formRepository->save($form);
    }
}
