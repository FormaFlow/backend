<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http\Resources;

use FormaFlow\Entries\Domain\EntryAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
{
    /** @var EntryAggregate */
    public $resource;

    public function __construct(EntryAggregate $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id()->value(),
            'form_id' => $this->resource->formId()->value(),
            'user_id' => $this->resource->userId(),
            'data' => $this->resource->data(),
            'score' => $this->resource->score(),
            'duration' => $this->resource->duration(),
            'created_at' => $this->resource->createdAt()->format('c'),
        ];
    }
}
