<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http\Resources;

use FormaFlow\Forms\Domain\FormAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicFormResource extends JsonResource
{
    /** @var FormAggregate */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id()->value(),
            'name' => $this->resource->name()->value(),
            'description' => $this->resource->description(),
            'published' => $this->resource->isPublished(),
            'is_quiz' => $this->resource->isQuiz(),
            'single_submission' => $this->resource->isSingleSubmission(),
            'fields_count' => count($this->resource->fields()),
            'fields' => array_map(static fn($field): array => [
                'id' => $field->id(),
                'label' => $field->label(),
                'type' => $field->type()->value(),
                'required' => $field->isRequired(),
                'options' => $field->options(),
                'unit' => $field->unit(),
                'order' => $field->order(),
                'points' => $field->points(),
            ], $this->resource->fields()),
        ];
    }
}
