<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Delete;

use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final readonly class DeleteFormCommandHandler
{
    public function __construct(
        private FormRepository $formRepository,
    ) {
    }

    public function handle(DeleteFormCommand $command): void
    {
        $formId = new FormId($command->id());
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            throw new InvalidArgumentException('Form not found');
        }

        $this->formRepository->delete($form);
    }
}
