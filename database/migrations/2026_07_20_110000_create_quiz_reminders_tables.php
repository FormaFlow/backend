<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forms', static function (Blueprint $table): void {
            $table->unsignedInteger('reminder_interval_minutes')->nullable();
        });

        Schema::create('push_subscriptions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('endpoint')->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 32)->default('aes128gcm');
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        Schema::create('quiz_assignments', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_id');
            $table->uuid('assigner_user_id');
            $table->uuid('recipient_user_id');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->foreign('assigner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['form_id', 'recipient_user_id']);
            $table->index(['completed_at', 'next_reminder_at']);
            $table->index('assigner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_assignments');
        Schema::dropIfExists('push_subscriptions');

        Schema::table('forms', static function (Blueprint $table): void {
            $table->dropColumn('reminder_interval_minutes');
        });
    }
};
