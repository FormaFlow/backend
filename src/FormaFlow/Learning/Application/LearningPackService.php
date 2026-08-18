<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Application;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormFieldModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningQuestionMetadataModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class LearningPackService
{
    public function __construct(private LearningAssessmentPublisher $publisher)
    {
    }

    public function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'schema' => 'required|in:formaflow.learning-pack.v1',
            'pack.external_id' => 'required|string|max:128',
            'pack.version' => 'required|integer|min:1',
            'pack.title' => 'required|string|min:3|max:255',
            'pack.description' => 'nullable|string',
            'pack.subject' => 'required|string|max:64',
            'pack.purpose' => 'required|in:diagnostic,practice,review',
            'pack.target_grade' => 'required|integer|min:1|max:11',
            'pack.coverage_from_grade' => 'required|integer|min:1|max:11',
            'pack.coverage_to_grade' => 'required|integer|min:1|max:11|gte:pack.coverage_from_grade',
            'pack.source.name' => 'required|string|max:255',
            'pack.source.type' => 'required|in:owned,licensed',
            'pack.source.usage_scope' => 'required|in:workspace,publishable',
            'pack.source.license_name' => 'nullable|string|max:255',
            'pack.source.url' => 'nullable|url|max:2048',
            'questions' => 'required|array|min:1|max:500',
            'questions.*.external_id' => 'required|string|max:128|distinct',
            'questions.*.prompt' => 'required|string|max:10000',
            'questions.*.type' => 'required|in:single_choice,multiple_choice,short_text,number,boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.answer_config' => 'required|array',
            'questions.*.points' => 'required|integer|min:1|max:1000',
            'questions.*.explanation' => 'required|string|max:10000',
            'questions.*.topic' => 'nullable|string|max:128',
            'questions.*.difficulty' => 'nullable|in:easy,normal,hard',
        ])->validate();

        return $validated;
    }

    public function preview(array $validated): array
    {
        return [
            'valid' => true,
            'summary' => [
                'external_id' => $validated['pack']['external_id'],
                'question_count' => count($validated['questions']),
                'max_points' => array_sum(array_column($validated['questions'], 'points')),
                'subject' => $validated['pack']['subject'],
                'target_grade' => $validated['pack']['target_grade'],
            ],
        ];
    }

    public function import(array $validated, string $workspaceId, string $userId): array
    {
        $existing = LearningAssessmentModel::query()
            ->where('workspace_id', $workspaceId)
            ->where('external_id', $validated['pack']['external_id'])
            ->first();
        if ($existing !== null) {
            return ['assessment' => $existing, 'version' => $existing->current_version, 'created' => false];
        }

        return DB::transaction(function () use ($validated, $workspaceId, $userId): array {
            $source = DB::table('content_sources')->where([
                'workspace_id' => $workspaceId,
                'name' => $validated['pack']['source']['name'],
                'source_type' => $validated['pack']['source']['type'],
            ])->first();
            $sourceId = $source?->id ?? (string)Str::uuid();
            if ($source === null) {
                DB::table('content_sources')->insert([
                    'id' => $sourceId,
                    'workspace_id' => $workspaceId,
                    'name' => $validated['pack']['source']['name'],
                    'source_type' => $validated['pack']['source']['type'],
                    'usage_scope' => $validated['pack']['source']['usage_scope'],
                    'license_name' => $validated['pack']['source']['license_name'] ?? null,
                    'source_url' => $validated['pack']['source']['url'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $form = FormModel::query()->create([
                'id' => (string)Str::uuid(),
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'name' => $validated['pack']['title'],
                'description' => $validated['pack']['description'] ?? null,
                'published' => false,
                'version' => 1,
                'is_quiz' => true,
                'single_submission' => false,
                'quick_entry_favorite' => false,
            ]);
            $assessment = LearningAssessmentModel::query()->create([
                'id' => (string)Str::uuid(),
                'workspace_id' => $workspaceId,
                'form_id' => $form->id,
                'source_id' => $sourceId,
                'created_by_user_id' => $userId,
                'external_id' => $validated['pack']['external_id'],
                'subject_code' => $validated['pack']['subject'],
                'purpose' => $validated['pack']['purpose'],
                'target_grade' => $validated['pack']['target_grade'],
                'coverage_from_grade' => $validated['pack']['coverage_from_grade'],
                'coverage_to_grade' => $validated['pack']['coverage_to_grade'],
                'status' => 'draft',
                'current_version' => 0,
            ]);
            foreach ($validated['questions'] as $order => $question) {
                $fieldId = (string)Str::uuid();
                FormFieldModel::query()->create([
                    'id' => $fieldId,
                    'form_id' => $form->id,
                    'label' => $question['prompt'],
                    'type' => $this->fieldType($question['type']),
                    'required' => true,
                    'options' => $question['options'] ?? null,
                    'order' => $order,
                    'correct_answer' => $this->legacyCorrectAnswer($question['answer_config']),
                    'points' => $question['points'],
                ]);
                LearningQuestionMetadataModel::query()->create([
                    'form_field_id' => $fieldId,
                    'assessment_id' => $assessment->id,
                    'external_id' => $question['external_id'],
                    'answer_type' => $question['type'],
                    'answer_config' => $question['answer_config'],
                    'explanation' => $question['explanation'],
                    'topic' => $question['topic'] ?? null,
                    'difficulty' => $question['difficulty'] ?? 'normal',
                ]);
            }
            $version = $this->publisher->publish($assessment, $userId);
            $assessment->refresh();

            return ['assessment' => $assessment, 'version' => $version->version_number, 'created' => true];
        });
    }

    private function fieldType(string $answerType): string
    {
        return match ($answerType) {
            'single_choice', 'multiple_choice' => 'select',
            'number' => 'number',
            'boolean' => 'boolean',
            default => 'text',
        };
    }

    private function legacyCorrectAnswer(array $config): ?string
    {
        $answers = $config['accepted'] ?? $config['correct'] ?? [];
        return count($answers) === 1 ? (string)$answers[0] : null;
    }
}
