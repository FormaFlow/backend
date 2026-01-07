<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('is_quiz')->default(false);
            $table->boolean('single_submission')->default(false);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->text('correct_answer')->nullable();
            $table->integer('points')->default(0);
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->integer('score')->nullable();
            $table->integer('duration')->nullable(); // Duration in seconds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['is_quiz', 'single_submission']);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn(['correct_answer', 'points']);
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['score', 'duration']);
        });
    }
};