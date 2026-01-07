<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Publish;

use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final readonly class PublishFormCommandHandler
{
    public function __construct(
        private FormRepository $repository,
    ) {
    }

    public function handle(PublishFormCommand $command): void
    {
        $formId = new FormId($command->formId());
        $form = $this->repository->findById($formId);

        if (!$form) {
            throw new InvalidArgumentException('Form not found');
        }

        $form->publish();
        $this->repository->save($form);
    }
}
