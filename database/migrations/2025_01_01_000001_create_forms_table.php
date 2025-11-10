<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('published')->default(false);
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
