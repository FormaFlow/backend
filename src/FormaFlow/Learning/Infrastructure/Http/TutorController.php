<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use FormaFlow\Learning\Application\TutorGateway;
use FormaFlow\Learning\Infrastructure\Persistence\Eloquent\LearningAssessmentVersionModel;
use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class TutorController
{
    public function __construct(private TutorGateway $tutor)
    {
    }

    public function explain(Request $request, string $workspaceId): JsonResponse
    {
        if (!$this->isMember($request, $workspaceId) || !$this->moduleEnabled($workspaceId)) {
            return response()->json(['message' => 'Tutor module is unavailable'], Response::HTTP_FORBIDDEN);
        }
        $validated = $request->validate([
            'attempt_id' => ['required', 'uuid'],
            'question_id' => ['required', 'uuid'],
            'message' => ['sometimes', 'string', 'max:2000'],
        ]);
        $attempt = DB::table('learning_attempts')->where([
            'id' => $validated['attempt_id'],
            'workspace_id' => $workspaceId,
            'learner_user_id' => $request->user()->id,
            'status' => 'completed',
        ])->first();
        if ($attempt === null) {
            return response()->json(['message' => 'Completed attempt not found'], Response::HTTP_NOT_FOUND);
        }
        $version = LearningAssessmentVersionModel::query()->findOrFail($attempt->assessment_version_id);
        $question = collect($version->snapshot['questions'])->firstWhere('id', $validated['question_id']);
        if ($question === null) {
            return response()->json(['message' => 'Question not found'], Response::HTTP_NOT_FOUND);
        }
        $response = DB::table('learning_responses')->where([
            'attempt_id' => $attempt->id, 'question_id' => $validated['question_id'],
        ])->first();
        $result = $this->tutor->explain([
            'workspace_id' => $workspaceId,
            'learner' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            'assessment' => ['id' => $attempt->assessment_id, 'version_id' => $attempt->assessment_version_id],
            'question' => $question,
            'response' => $response === null ? null : [
                'answer' => is_string($response->answer) ? json_decode($response->answer, true, 512, JSON_THROW_ON_ERROR) : $response->answer,
                'is_correct' => (bool)$response->is_correct,
            ],
        ], $validated['message'] ?? 'Объясни мою ошибку простыми словами.');

        return response()->json(['tutor' => $result]);
    }

    private function isMember(Request $request, string $workspaceId): bool
    {
        return WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $request->user()->id, 'status' => 'active',
        ])->exists();
    }

    private function moduleEnabled(string $workspaceId): bool
    {
        return DB::table('workspace_modules')->where([
            'workspace_id' => $workspaceId, 'module_key' => 'tutor', 'enabled' => true,
        ])->exists();
    }
}
