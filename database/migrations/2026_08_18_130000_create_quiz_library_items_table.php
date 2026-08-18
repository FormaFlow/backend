<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_library_items', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_id');
            $table->uuid('user_id');
            $table->timestamp('last_opened_at');
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('forms')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['form_id', 'user_id']);
            $table->index(['user_id', 'last_opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_library_items');
    }
};
