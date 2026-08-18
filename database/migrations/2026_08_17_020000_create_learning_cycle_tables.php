<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('learning_assignments', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('assessment_id');
            $table->uuid('assessment_version_id');
            $table->uuid('learner_user_id');
            $table->uuid('assigned_by_user_id');
            $table->string('status', 24)->default('assigned');
            $table->timestamp('assigned_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('learning_assessments')->cascadeOnDelete();
            $table->foreign('assessment_version_id')->references('id')->on('learning_assessment_versions')->restrictOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['workspace_id', 'learner_user_id', 'status']);
        });

        Schema::create('learning_attempts', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('assignment_id');
            $table->uuid('assessment_id');
            $table->uuid('assessment_version_id');
            $table->uuid('learner_user_id');
            $table->uuid('entry_id')->nullable()->unique();
            $table->string('status', 24)->default('in_progress');
            $table->integer('score')->nullable();
            $table->unsignedInteger('max_points');
            $table->string('submission_key')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('assignment_id')->references('id')->on('learning_assignments')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('learning_assessments')->cascadeOnDelete();
            $table->foreign('assessment_version_id')->references('id')->on('learning_assessment_versions')->restrictOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('entry_id')->references('id')->on('entries')->nullOnDelete();
            $table->index(['workspace_id', 'learner_user_id', 'status']);
        });

        Schema::create('learning_responses', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->jsonb('answer')->nullable();
            $table->boolean('is_correct');
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedInteger('max_points')->default(0);
            $table->timestamps();
            $table->foreign('attempt_id')->references('id')->on('learning_attempts')->cascadeOnDelete();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('learning_review_items', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('learner_user_id');
            $table->uuid('assessment_id');
            $table->uuid('assessment_version_id');
            $table->uuid('question_id');
            $table->jsonb('question_snapshot');
            $table->unsignedSmallInteger('stage')->default(0);
            $table->string('status', 24)->default('due');
            $table->timestamp('next_review_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('learning_assessments')->cascadeOnDelete();
            $table->foreign('assessment_version_id')->references('id')->on('learning_assessment_versions')->restrictOnDelete();
            $table->unique(['workspace_id', 'learner_user_id', 'question_id']);
            $table->index(['learner_user_id', 'status', 'next_review_at']);
        });

        Schema::create('learning_review_attempts', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('review_item_id');
            $table->string('idempotency_key', 128);
            $table->jsonb('answer')->nullable();
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('resulting_stage');
            $table->string('resulting_status', 24);
            $table->timestamp('resulting_next_review_at')->nullable();
            $table->timestamps();
            $table->foreign('review_item_id')->references('id')->on('learning_review_items')->cascadeOnDelete();
            $table->unique(['review_item_id', 'idempotency_key']);
        });

        Schema::create('xp_ledger', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('learner_user_id');
            $table->string('reason', 64);
            $table->string('source_type', 48);
            $table->string('source_id', 128);
            $table->integer('points');
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['learner_user_id', 'source_type', 'source_id']);
            $table->index(['workspace_id', 'learner_user_id', 'created_at']);
        });

        Schema::create('learner_streaks', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('learner_user_id');
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'learner_user_id']);
        });

        Schema::create('achievement_awards', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('learner_user_id');
            $table->string('achievement_code', 64);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'learner_user_id', 'achievement_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_awards');
        Schema::dropIfExists('learner_streaks');
        Schema::dropIfExists('xp_ledger');
        Schema::dropIfExists('learning_review_attempts');
        Schema::dropIfExists('learning_review_items');
        Schema::dropIfExists('learning_responses');
        Schema::dropIfExists('learning_attempts');
        Schema::dropIfExists('learning_assignments');
    }
};
