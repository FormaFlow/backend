<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_schedules', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('learner_user_id');
            $table->uuid('guardian_user_id');
            $table->string('timezone', 64);
            $table->time('daily_time');
            $table->jsonb('weekdays');
            $table->unsignedInteger('guardian_delay_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('learner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('guardian_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'learner_user_id']);
        });

        Schema::create('learning_notification_log', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('schedule_id');
            $table->string('kind', 32);
            $table->date('local_date');
            $table->string('status', 32);
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->foreign('schedule_id')->references('id')->on('study_schedules')->cascadeOnDelete();
            $table->unique(['schedule_id', 'kind', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_notification_log');
        Schema::dropIfExists('study_schedules');
    }
};
