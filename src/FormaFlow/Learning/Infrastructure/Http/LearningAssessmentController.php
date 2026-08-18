<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use FormaFlow\Learning\Application\LearningAssessmentPublisher;
use FormaFlow\Learning\Application\LearningPackService;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentVersionModel;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningQuestionMetadataModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final readonly class LearningAssessmentController
{
    public function __construct(
        private LearningPackService $packs,
        private LearningAssessmentPublisher $publisher,
    ) {
    }

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $assessments = LearningAssessmentModel::query()
            ->with('form:id,name,description')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn(LearningAssessmentModel $assessment): array => $this->serializeAssessment($assessment) + [
                'title' => $assessment->form->name,
                'description' => $assessment->form->description,
                'coverage_from_grade' => $assessment->coverage_from_grade,
                'coverage_to_grade' => $assessment->coverage_to_grade,
            ]);

        return response()->json(['assessments' => $assessments]);
    }

    public function editor(Request $request, string $workspaceId, string $assessmentId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $assessment = LearningAssessmentModel::query()
            ->with(['form.fields', 'questions'])
            ->where('workspace_id', $workspaceId)
            ->find($assessmentId);
        if ($assessment === null) {
            return response()->json(['message' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }
        $metadata = $assessment->questions->keyBy('form_field_id');
        $questions = $assessment->form->fields->map(function ($field) use ($metadata): array {
            $question = $metadata->get($field->id);
            return [
                'id' => $field->id,
                'external_id' => $question?->external_id,
                'prompt' => $field->label,
                'type' => $question?->answer_type,
                'options' => $field->options,
                'answer_config' => $question?->answer_config,
                'explanation' => $question?->explanation,
                'topic' => $question?->topic,
                'difficulty' => $question?->difficulty,
                'prompt_media_id' => $question?->prompt_media_id,
                'prompt_media_url' => $this->mediaUrl($question?->prompt_media_id),
                'points' => (int)$field->points,
                'required' => (bool)$field->required,
            ];
        })->values();

        return response()->json(['assessment' => $this->serializeAssessment($assessment) + [
            'title' => $assessment->form->name,
            'description' => $assessment->form->description,
            'questions' => $questions,
        ]]);
    }

    public function preview(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        return response()->json($this->packs->preview($this->packs->validate($request->all())));
    }

    public function library(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        return response()->json(['packs' => array_values(array_map(
            static fn(array $pack): array => array_diff_key($pack, ['file' => true]),
            $this->builtInPacks(),
        ))]);
    }

    public function installBuiltIn(Request $request, string $workspaceId, string $packId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $packs = $this->builtInPacks();
        if (!isset($packs[$packId])) {
            return response()->json(['message' => 'Pack not found'], Response::HTTP_NOT_FOUND);
        }
        $payload = json_decode((string)file_get_contents(resource_path('learning-packs/' . $packs[$packId]['file'])), true, 512, JSON_THROW_ON_ERROR);
        $result = $this->packs->import($this->packs->validate($payload), $workspaceId, $request->user()->id);
        return response()->json([
            'assessment' => $this->serializeAssessment($result['assessment']),
            'version' => $result['version'],
            'created' => $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function import(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $result = $this->packs->import($this->packs->validate($request->all()), $workspaceId, $request->user()->id);
        return response()->json([
            'assessment' => $this->serializeAssessment($result['assessment']),
            'version' => $result['version'],
            'created' => $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function currentVersion(Request $request, string $workspaceId, string $assessmentId): JsonResponse
    {
        if (!$this->isMember($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $assessment = LearningAssessmentModel::query()
            ->where('workspace_id', $workspaceId)
            ->find($assessmentId);
        if ($assessment === null || $assessment->current_version < 1) {
            return response()->json(['message' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }
        $version = LearningAssessmentVersionModel::query()
            ->where('assessment_id', $assessmentId)
            ->where('version_number', $assessment->current_version)
            ->firstOrFail();
        $snapshot = $version->snapshot;
        $snapshot['version'] = $version->version_number;
        $snapshot['max_points'] = $version->max_points;
        $snapshot['questions'] = array_map(function (array $question): array {
            unset($question['answer_config'], $question['explanation'], $question['explanation_media_id']);
            $question['prompt_media_url'] = $this->mediaUrl($question['prompt_media_id'] ?? null);
            return $question;
        }, $snapshot['questions']);

        return response()->json(['assessment' => $snapshot]);
    }

    public function updateQuestion(Request $request, string $workspaceId, string $assessmentId, string $fieldId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $assessment = LearningAssessmentModel::query()->where('workspace_id', $workspaceId)->find($assessmentId);
        $question = LearningQuestionMetadataModel::query()
            ->where('assessment_id', $assessmentId)
            ->where('form_field_id', $fieldId)
            ->first();
        if ($assessment === null || $question === null) {
            return response()->json(['message' => 'Question not found'], Response::HTTP_NOT_FOUND);
        }
        $validated = $request->validate([
            'prompt' => 'sometimes|string|max:10000',
            'answer_config' => 'sometimes|array',
            'explanation' => 'sometimes|string|max:10000',
            'topic' => 'sometimes|nullable|string|max:128',
            'prompt_media_id' => 'sometimes|nullable|uuid',
        ]);
        if (
            !empty($validated['prompt_media_id']) && !DB::table('media_assets')->where([
            'id' => $validated['prompt_media_id'], 'workspace_id' => $workspaceId,
            ])->exists()
        ) {
            return response()->json(['message' => 'Media asset not found'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (isset($validated['prompt'])) {
            $assessment->form()->firstOrFail()->fields()->where('id', $fieldId)->update(['label' => $validated['prompt']]);
        }
        $question->update(array_filter([
            'answer_config' => $validated['answer_config'] ?? null,
            'explanation' => $validated['explanation'] ?? null,
            'topic' => array_key_exists('topic', $validated) ? $validated['topic'] : null,
        ], static fn($value): bool => $value !== null));
        if (array_key_exists('prompt_media_id', $validated)) {
            $question->update(['prompt_media_id' => $validated['prompt_media_id']]);
        }
        $assessment->update(['status' => 'draft']);

        return response()->json(['message' => 'Question updated']);
    }

    public function publish(Request $request, string $workspaceId, string $assessmentId): JsonResponse
    {
        if (!$this->canManage($request, $workspaceId)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $assessment = LearningAssessmentModel::query()->where('workspace_id', $workspaceId)->find($assessmentId);
        if ($assessment === null) {
            return response()->json(['message' => 'Assessment not found'], Response::HTTP_NOT_FOUND);
        }
        $version = $this->publisher->publish($assessment, $request->user()->id);
        return response()->json(['version' => $version->version_number]);
    }

    private function serializeAssessment(LearningAssessmentModel $assessment): array
    {
        return [
            'id' => $assessment->id,
            'external_id' => $assessment->external_id,
            'subject' => $assessment->subject_code,
            'purpose' => $assessment->purpose,
            'target_grade' => $assessment->target_grade,
            'status' => $assessment->status,
            'current_version' => $assessment->current_version,
        ];
    }

    private function builtInPacks(): array
    {
        return [
            'grade-1-math-foundation-100' => [
                'id' => 'grade-1-math-foundation-100', 'file' => 'grade-1-math-foundation-100.ru.json',
                'title' => 'Математика: уверенная база за 1 класс',
                'description' => '100 оригинальных вопросов FormaFlow', 'subject' => 'math',
                'questions' => 100, 'target_grade' => 2,
            ],
            'grade-4-math-diagnostic' => [
                'id' => 'grade-4-math-diagnostic', 'file' => 'grade-4-math-diagnostic.ru.json',
                'title' => 'Математика: диагностика за 1–4 классы',
                'description' => '30 оригинальных вопросов FormaFlow', 'subject' => 'math',
                'questions' => 30, 'target_grade' => 5,
            ],
            'grade-4-russian-diagnostic' => [
                'id' => 'grade-4-russian-diagnostic', 'file' => 'grade-4-russian-diagnostic.ru.json',
                'title' => 'Русский язык: диагностика за 1–4 классы',
                'description' => '25 оригинальных вопросов FormaFlow', 'subject' => 'russian',
                'questions' => 25, 'target_grade' => 5,
            ],
            'grade-4-math-advanced' => [
                'id' => 'grade-4-math-advanced', 'file' => 'grade-4-math-advanced.ru.json',
                'title' => 'Математика: вступительная работа после 4 класса',
                'description' => '30 оригинальных вопросов повышенной сложности', 'subject' => 'math',
                'questions' => 30, 'target_grade' => 5,
            ],
            'grade-4-russian-advanced' => [
                'id' => 'grade-4-russian-advanced', 'file' => 'grade-4-russian-advanced.ru.json',
                'title' => 'Русский язык: вступительная работа после 4 класса',
                'description' => '25 оригинальных вопросов повышенной сложности', 'subject' => 'russian',
                'questions' => 25, 'target_grade' => 5,
            ],
            'grade-9-math-diagnostic' => [
                'id' => 'grade-9-math-diagnostic', 'file' => 'grade-9-math-diagnostic.ru.json',
                'title' => 'Математика: диагностика за 9 класс',
                'description' => '30 оригинальных вопросов FormaFlow', 'subject' => 'math',
                'questions' => 30, 'target_grade' => 9,
            ],
        ];
    }

    private function mediaUrl(?string $assetId): ?string
    {
        if ($assetId === null) {
            return null;
        }
        $asset = DB::table('media_assets')->where('id', $assetId)->first(['disk', 'path']);
        return $asset === null ? null : Storage::disk($asset->disk)->url($asset->path);
    }

    private function canManage(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId,
            'user_id' => $request->user()->id,
            'status' => 'active',
        ])->whereIn('role', ['owner', 'admin'])->exists();
    }

    private function isMember(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId,
            'user_id' => $request->user()->id,
            'status' => 'active',
        ])->exists();
    }
}
