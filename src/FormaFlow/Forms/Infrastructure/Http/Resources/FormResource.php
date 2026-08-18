<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http\Resources;

use FormaFlow\Forms\Domain\FormAggregate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** @mixin FormAggregate */
final class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canRevealAnswers = $request->user()?->id === $this->userId();
        $learningMetadata = collect();
        if ($canRevealAnswers && Schema::hasTable('learning_question_metadata')) {
            $learningMetadata = DB::table('learning_question_metadata')
                ->whereIn('form_field_id', array_map(static fn($field): string => $field->id(), $this->fields()))
                ->get()
                ->keyBy('form_field_id');
        }
        $fieldsData = [];
        foreach ($this->fields() as $field) {
            $fieldData = [
                'id' => $field->id(),
                'label' => $field->label(),
                'type' => $field->type()->value(),
                'sum_values' => $field->sumValues(),
                'trend_direction' => $field->trendDirection(),
                'required' => $field->isRequired(),
                'options' => $field->options(),
                'unit' => $field->unit(),
                'category' => $field->category(),
                'order' => $field->order(),
                'points' => $field->points(),
            ];
            if ($canRevealAnswers) {
                $metadata = $learningMetadata->get($field->id());
                $fieldData['correctAnswer'] = $field->correctAnswer();
                $fieldData['answerConfig'] = $metadata === null
                    ? null
                    : $this->decodeJson($metadata->answer_config);
                $fieldData['explanation'] = $metadata?->explanation;
            }
            $fieldsData[] = $fieldData;
        }

        return [
            'id' => $this->id()->value(),
            'name' => $this->name()->value(),
            'description' => $this->description(),
            'published' => $this->isPublished(),
            'is_quiz' => $this->isQuiz(),
            'single_submission' => $this->isSingleSubmission(),
            'quick_entry_favorite' => $this->isQuickEntryFavorite(),
            'reminder_interval_minutes' => $this->reminderIntervalMinutes(),
            'fields_count' => count($this->fields()),
            'fields' => $fieldsData,
        ];
    }

    private function decodeJson(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
    }
}
