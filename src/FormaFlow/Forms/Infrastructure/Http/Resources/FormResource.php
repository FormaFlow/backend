<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http\Resources;

use FormaFlow\Forms\Domain\FormAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormAggregate */
final class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fieldsData = [];
        foreach ($this->fields() as $field) {
            $fieldsData[] = [
                'id' => $field->id(),
                'label' => $field->label(),
                'type' => $field->type()->value(),
                'required' => $field->isRequired(),
                'options' => $field->options(),
                'unit' => $field->unit(),
                'category' => $field->category(),
                'order' => $field->order(),
                'correctAnswer' => $field->correctAnswer(),
                'points' => $field->points(),
            ];
        }

        return [
            'id' => $this->id()->value(),
            'name' => $this->name()->value(),
            'description' => $this->description(),
            'published' => $this->isPublished(),
            'is_quiz' => $this->isQuiz(),
            'single_submission' => $this->isSingleSubmission(),
            'fields_count' => count($this->fields()),
            'fields' => $fieldsData,
        ];
    }
}
