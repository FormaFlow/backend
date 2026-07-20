<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http\Resources;

use FormaFlow\Forms\Domain\FormSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormSummary */
final class FormSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'published' => $this->published,
            'is_quiz' => $this->isQuiz,
            'single_submission' => $this->singleSubmission,
            'quick_entry_favorite' => $this->quickEntryFavorite,
            'fields_count' => $this->fieldsCount,
            'entries_count' => $this->entriesCount,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
