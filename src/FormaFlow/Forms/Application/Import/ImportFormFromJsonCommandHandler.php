<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Import;

use FormaFlow\Forms\Domain\Field;
use FormaFlow\Forms\Domain\FieldType;
use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Domain\FormRepository;
use Shared\Infrastructure\Uuid;

final readonly class ImportFormFromJsonCommandHandler
{
    public function __construct(
        private FormRepository $repository
    ) {
    }

    public function handle(ImportFormFromJsonCommand $command): string
    {
        $data = $command->data();
        $id = Uuid::generate();

        $form = new FormAggregate(
            id: new FormId($id),
            userId: $command->userId(),
            name: new FormName($data['name']),
            description: $data['description'] ?? null
        );

        // Update settings if provided
        $form->updateSettings(
            isQuiz: $data['is_quiz'] ?? false,
            singleSubmission: $data['single_submission'] ?? false
        );

        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $index => $fieldData) {
                // Handle both snake_case (JSON typical) and camelCase (internal) keys for flexibility
                $correctAnswer = $fieldData['correct_answer'] ?? $fieldData['correctAnswer'] ?? null;
                $order = $fieldData['order'] ?? $index;

                $field = new Field(
                    id: Uuid::generate(),
                    label: $fieldData['label'],
                    type: new FieldType($fieldData['type']),
                    required: $fieldData['required'] ?? false,
                    options: $fieldData['options'] ?? null,
                    unit: $fieldData['unit'] ?? null,
                    category: $fieldData['category'] ?? null,
                    order: (int)$order,
                    correctAnswer: $correctAnswer,
                    points: (int)($fieldData['points'] ?? 0)
                );
                $form->addField($field);
            }
        }

        // Auto-publish if requested
        if (isset($data['published']) && $data['published'] === true) {
            $form->publish();
        }

        $this->repository->save($form);

        return $id;
    }
}
