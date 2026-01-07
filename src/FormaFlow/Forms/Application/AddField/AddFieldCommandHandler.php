<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\AddField;

use FormaFlow\Forms\Domain\Field;
use FormaFlow\Forms\Domain\FieldType;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use InvalidArgumentException;

final readonly class AddFieldCommandHandler
{
    public function __construct(
        private FormRepository $repository,
    ) {
    }

    public function handle(AddFieldCommand $command): void
    {
        $formId = new FormId($command->formId());
        $form = $this->repository->findById($formId);

        if (!$form) {
            throw new InvalidArgumentException('Form not found');
        }

        $field = new Field(
            id: $command->fieldId(),
            label: $command->label(),
            type: new FieldType($command->type()),
            required: $command->isRequired(),
            options: $command->options(),
            unit: $command->unit(),
            category: $command->category(),
            order: $command->order(),
            correctAnswer: $command->correctAnswer(),
            points: $command->points(),
        );

        $form->addField($field);
        $this->repository->save($form);
    }
}
