<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_sources', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->string('source_type', 32);
            $table->string('usage_scope', 32);
            $table->string('license_name')->nullable();
            $table->text('source_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'name', 'source_type']);
        });

        Schema::create('media_assets', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('uploaded_by_user_id');
            $table->string('disk', 32)->default('public');
            $table->text('path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->string('alt_text')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'checksum']);
        });

        Schema::create('learning_assessments', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('form_id')->unique();
            $table->uuid('source_id')->nullable();
            $table->uuid('created_by_user_id');
            $table->string('external_id')->nullable();
            $table->string('subject_code', 64);
            $table->string('purpose', 32)->default('diagnostic');
            $table->unsignedSmallInteger('target_grade');
            $table->unsignedSmallInteger('coverage_from_grade');
            $table->unsignedSmallInteger('coverage_to_grade');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('content_sources')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'external_id']);
            $table->index(['workspace_id', 'subject_code', 'target_grade']);
        });

        Schema::create('learning_question_metadata', static function (Blueprint $table): void {
            $table->uuid('form_field_id')->primary();
            $table->uuid('assessment_id');
            $table->string('external_id')->nullable();
            $table->string('answer_type', 32);
            $table->jsonb('answer_config');
            $table->text('explanation')->nullable();
            $table->string('topic', 128)->nullable();
            $table->string('difficulty', 32)->default('normal');
            $table->uuid('prompt_media_id')->nullable();
            $table->uuid('explanation_media_id')->nullable();
            $table->timestamps();
            $table->foreign('form_field_id')->references('id')->on('form_fields')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('learning_assessments')->cascadeOnDelete();
            $table->foreign('prompt_media_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->foreign('explanation_media_id')->references('id')->on('media_assets')->nullOnDelete();
            $table->unique(['assessment_id', 'external_id']);
        });

        Schema::create('learning_assessment_versions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->unsignedInteger('version_number');
            $table->jsonb('snapshot');
            $table->unsignedInteger('max_points');
            $table->uuid('published_by_user_id');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->foreign('assessment_id')->references('id')->on('learning_assessments')->cascadeOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['assessment_id', 'version_number']);
        });

        $this->backfillLegacyQuizzes();
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_assessment_versions');
        Schema::dropIfExists('learning_question_metadata');
        Schema::dropIfExists('learning_assessments');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('content_sources');
    }

    private function backfillLegacyQuizzes(): void
    {
        $now = now();
        foreach (DB::table('forms')->where('is_quiz', true)->whereNotNull('workspace_id')->get() as $form) {
            $sourceId = DB::table('content_sources')
                ->where('workspace_id', $form->workspace_id)
                ->where('name', 'Legacy FormaFlow')
                ->value('id');
            if ($sourceId === null) {
                $sourceId = (string)Str::uuid();
                DB::table('content_sources')->insert([
                    'id' => $sourceId,
                    'workspace_id' => $form->workspace_id,
                    'name' => 'Legacy FormaFlow',
                    'source_type' => 'owned',
                    'usage_scope' => 'workspace',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $assessmentId = (string)Str::uuid();
            DB::table('learning_assessments')->insert([
                'id' => $assessmentId,
                'workspace_id' => $form->workspace_id,
                'form_id' => $form->id,
                'source_id' => $sourceId,
                'created_by_user_id' => $form->user_id,
                'external_id' => 'legacy-' . $form->id,
                'subject_code' => 'general',
                'purpose' => 'diagnostic',
                'target_grade' => 1,
                'coverage_from_grade' => 1,
                'coverage_to_grade' => 1,
                'status' => $form->published ? 'published' : 'draft',
                'current_version' => $form->published ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $questions = [];
            foreach (DB::table('form_fields')->where('form_id', $form->id)->orderBy('order')->get() as $field) {
                $answerType = match ($field->type) {
                    'select' => 'single_choice',
                    'number', 'currency' => 'number',
                    'boolean' => 'boolean',
                    default => 'short_text',
                };
                $config = ['accepted' => $field->correct_answer !== null ? [(string)$field->correct_answer] : []];
                DB::table('learning_question_metadata')->insert([
                    'form_field_id' => $field->id,
                    'assessment_id' => $assessmentId,
                    'external_id' => 'legacy-' . $field->id,
                    'answer_type' => $answerType,
                    'answer_config' => json_encode($config, JSON_THROW_ON_ERROR),
                    'topic' => $field->category,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $questions[] = [
                    'id' => $field->id,
                    'prompt' => $field->label,
                    'type' => $answerType,
                    'options' => $field->options !== null ? json_decode($field->options, true) : null,
                    'answer_config' => $config,
                    'explanation' => null,
                    'topic' => $field->category,
                    'points' => (int)$field->points,
                    'required' => (bool)$field->required,
                ];
            }
            if ($form->published) {
                $snapshot = [
                    'assessment_id' => $assessmentId,
                    'title' => $form->name,
                    'description' => $form->description,
                    'subject' => 'general',
                    'purpose' => 'diagnostic',
                    'target_grade' => 1,
                    'coverage_from_grade' => 1,
                    'coverage_to_grade' => 1,
                    'questions' => $questions,
                ];
                DB::table('learning_assessment_versions')->insert([
                    'id' => (string)Str::uuid(),
                    'assessment_id' => $assessmentId,
                    'version_number' => 1,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    'max_points' => array_sum(array_column($questions, 'points')),
                    'published_by_user_id' => $form->user_id,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
