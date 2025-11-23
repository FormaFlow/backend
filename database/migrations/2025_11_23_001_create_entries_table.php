<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entries', static function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('form_id');
            $table->string('user_id');
            $table->json('data');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['form_id', 'user_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
