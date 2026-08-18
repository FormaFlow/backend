<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Application;

use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentVersionModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LearningAssessmentPublisher
{
    public function publish(LearningAssessmentModel $assessment, string $userId): LearningAssessmentVersionModel
    {
        return DB::transaction(function () use ($assessment, $userId): LearningAssessmentVersionModel {
            $assessment->load(['form.fields', 'questions']);
            $metadata = $assessment->questions->keyBy('form_field_id');
            $questions = [];
            foreach ($assessment->form->fields as $field) {
                $question = $metadata->get($field->id);
                if ($question === null) {
                    continue;
                }
                $questions[] = [
                    'id' => $field->id,
                    'external_id' => $question->external_id,
                    'prompt' => $field->label,
                    'type' => $question->answer_type,
                    'options' => $field->options,
                    'answer_config' => $question->answer_config,
                    'explanation' => $question->explanation,
                    'topic' => $question->topic,
                    'difficulty' => $question->difficulty,
                    'prompt_media_id' => $question->prompt_media_id,
                    'explanation_media_id' => $question->explanation_media_id,
                    'points' => (int)$field->points,
                    'required' => (bool)$field->required,
                    'order' => (int)$field->order,
                ];
            }
            if ($questions === []) {
                throw new RuntimeException('Cannot publish an assessment without questions.');
            }
            usort($questions, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
            $versionNumber = $assessment->current_version + 1;
            $snapshot = [
                'assessment_id' => $assessment->id,
                'title' => $assessment->form->name,
                'description' => $assessment->form->description,
                'subject' => $assessment->subject_code,
                'purpose' => $assessment->purpose,
                'target_grade' => $assessment->target_grade,
                'coverage_from_grade' => $assessment->coverage_from_grade,
                'coverage_to_grade' => $assessment->coverage_to_grade,
                'questions' => $questions,
            ];
            $version = LearningAssessmentVersionModel::query()->create([
                'id' => (string)Str::uuid(),
                'assessment_id' => $assessment->id,
                'version_number' => $versionNumber,
                'snapshot' => $snapshot,
                'max_points' => array_sum(array_column($questions, 'points')),
                'published_by_user_id' => $userId,
                'published_at' => now(),
            ]);
            $assessment->update(['status' => 'published', 'current_version' => $versionNumber]);
            $assessment->form->update([
                'published' => true,
                'version' => max((int)$assessment->form->version, $versionNumber),
            ]);

            return $version;
        });
    }
}
