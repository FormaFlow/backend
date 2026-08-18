<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class LearningAssessmentApiTest extends TestCase
{
    public function test_owner_can_preview_and_idempotently_import_a_versioned_learning_pack(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $pack = $this->pack();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import/preview", $pack)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.question_count', 2)
            ->assertJsonPath('summary.max_points', 20);
        $this->assertDatabaseMissing('learning_assessments', ['external_id' => 'grade-1-math-demo']);

        $first = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $pack)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('version', 1)
            ->assertJsonPath('created', true);

        $assessmentId = $first->json('assessment.id');
        $this->assertDatabaseHas('learning_assessments', [
            'id' => $assessmentId,
            'workspace_id' => $workspaceId,
            'external_id' => 'grade-1-math-demo',
            'subject_code' => 'math',
            'current_version' => 1,
        ]);
        self::assertSame(1, DB::table('learning_assessment_versions')->where('assessment_id', $assessmentId)->count());
        self::assertSame(2, DB::table('learning_question_metadata')->where('assessment_id', $assessmentId)->count());

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $pack)
            ->assertOk()
            ->assertJsonPath('assessment.id', $assessmentId)
            ->assertJsonPath('created', false);
        self::assertSame(1, DB::table('learning_assessment_versions')->where('assessment_id', $assessmentId)->count());
    }

    public function test_learner_payload_hides_answers_and_explanations(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $import = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $this->pack())
            ->assertCreated();
        $assessmentId = $import->json('assessment.id');
        $formId = DB::table('learning_assessments')->where('id', $assessmentId)->value('form_id');
        $learner = $this->addLearner($workspaceId, $owner->id);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/forms/{$formId}")
            ->assertOk()
            ->assertJsonPath('fields.0.answerConfig.accepted.0', '5');
        $this->actingAs($learner, 'sanctum')
            ->getJson("/api/v1/forms/{$formId}")
            ->assertOk()
            ->assertJsonMissingPath('fields.0.answerConfig')
            ->assertJsonMissingPath('fields.0.correctAnswer');

        $this->actingAs($learner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/versions/current")
            ->assertOk()
            ->assertJsonPath('assessment.version', 1)
            ->assertJsonPath('assessment.questions.0.prompt', 'Сколько будет 2 + 3?')
            ->assertJsonMissingPath('assessment.questions.0.answer_config')
            ->assertJsonMissingPath('assessment.questions.0.explanation');
    }

    public function test_new_publish_keeps_previous_snapshot_immutable(): void
    {
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $import = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $this->pack())
            ->assertCreated();
        $assessmentId = $import->json('assessment.id');
        $fieldId = DB::table('learning_question_metadata')
            ->where('assessment_id', $assessmentId)
            ->orderBy('created_at')
            ->value('form_field_id');

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/questions/{$fieldId}", [
                'prompt' => 'Сколько будет 3 + 3?',
                'explanation' => 'Три плюс три равно шести.',
                'answer_config' => ['accepted' => ['6']],
            ])
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/publish")
            ->assertOk()
            ->assertJsonPath('version', 2);

        $versions = DB::table('learning_assessment_versions')
            ->where('assessment_id', $assessmentId)
            ->orderBy('version_number')
            ->get();
        self::assertCount(2, $versions);
        self::assertSame('Сколько будет 2 + 3?', json_decode($versions[0]->snapshot, true)['questions'][0]['prompt']);
        self::assertSame('Сколько будет 3 + 3?', json_decode($versions[1]->snapshot, true)['questions'][0]['prompt']);
    }

    public function test_non_member_cannot_import_content(): void
    {
        [, $workspaceId] = $this->ownerWorkspace();
        $outsider = UserModel::factory()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $this->pack())
            ->assertForbidden();
    }

    public function test_owner_can_attach_an_original_image_to_a_question(): void
    {
        Storage::fake('public');
        [$owner, $workspaceId] = $this->ownerWorkspace();
        $assessmentId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/import", $this->pack())
            ->assertCreated()->json('assessment.id');
        $fieldId = DB::table('learning_question_metadata')->where('assessment_id', $assessmentId)->value('form_field_id');
        $asset = $this->actingAs($owner, 'sanctum')
            ->post("/api/v1/workspaces/{$workspaceId}/learning/media", [
                'file' => UploadedFile::fake()->image('diagram.png', 320, 200),
                'alt_text' => 'Пять кружков',
            ], ['Accept' => 'application/json'])
            ->assertCreated();
        $assetId = $asset->json('asset.id');

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/questions/{$fieldId}", [
                'prompt_media_id' => $assetId,
            ])->assertOk();
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/publish")
            ->assertOk();
        $learner = $this->addLearner($workspaceId, $owner->id);
        $this->actingAs($learner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspaceId}/learning/assessments/{$assessmentId}/versions/current")
            ->assertOk()
            ->assertJsonPath('assessment.questions.0.prompt_media_id', $assetId)
            ->assertJsonPath('assessment.questions.0.prompt_media_url', Storage::disk('public')->url(DB::table('media_assets')->where('id', $assetId)->value('path')));
    }

    private function pack(): array
    {
        return [
            'schema' => 'formaflow.learning-pack.v1',
            'pack' => [
                'external_id' => 'grade-1-math-demo',
                'version' => 1,
                'title' => 'Математика за 1 класс',
                'description' => 'Диагностика',
                'subject' => 'math',
                'purpose' => 'diagnostic',
                'target_grade' => 2,
                'coverage_from_grade' => 1,
                'coverage_to_grade' => 1,
                'source' => [
                    'name' => 'FormaFlow original',
                    'type' => 'owned',
                    'usage_scope' => 'publishable',
                ],
            ],
            'questions' => [
                [
                    'external_id' => 'math-001',
                    'prompt' => 'Сколько будет 2 + 3?',
                    'type' => 'number',
                    'answer_config' => ['accepted' => ['5']],
                    'points' => 10,
                    'explanation' => 'Два предмета и ещё три дают пять.',
                    'topic' => 'addition',
                ],
                [
                    'external_id' => 'math-002',
                    'prompt' => 'Какое число больше?',
                    'type' => 'single_choice',
                    'options' => [
                        ['label' => '4', 'value' => '4'],
                        ['label' => '7', 'value' => '7'],
                    ],
                    'answer_config' => ['correct' => ['7']],
                    'points' => 10,
                    'explanation' => 'Семь находится правее четырёх на числовой прямой.',
                    'topic' => 'comparison',
                ],
            ],
        ];
    }

    /** @return array{UserModel, string} */
    private function ownerWorkspace(): array
    {
        $owner = UserModel::factory()->create();
        $workspaceId = (string)Str::uuid();
        $now = now();
        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Family',
            'slug' => 'family-' . Str::lower(Str::random(8)),
            'type' => 'family',
            'timezone' => 'Europe/Moscow',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_memberships')->insert([
            'id' => (string)Str::uuid(),
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return [$owner, $workspaceId];
    }

    private function addLearner(string $workspaceId, string $ownerId): UserModel
    {
        $learner = UserModel::factory()->create(['account_type' => 'managed_learner', 'email' => null]);
        DB::table('workspace_memberships')->insert([
            'id' => (string)Str::uuid(),
            'workspace_id' => $workspaceId,
            'user_id' => $learner->id,
            'role' => 'learner',
            'status' => 'active',
            'managed_by_user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $learner;
    }
}
