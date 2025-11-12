<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_fields', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('form_id');
            $table->string('name');
            $table->string('label');
            $table->enum('type', ['text', 'number', 'date', 'boolean', 'select', 'currency', 'email']);
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->string('unit')->nullable();
            $table->string('category')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('forms')->onDelete('cascade');
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
