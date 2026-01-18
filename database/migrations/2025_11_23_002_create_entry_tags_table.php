<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entry_tags', static function (Blueprint $table) {
            $table->uuid('entry_id');
            $table->string('tag');
            $table->timestamps();

            $table->foreign('entry_id')
                ->references('id')
                ->on('entries')
                ->onDelete('cascade');

            $table->primary(['entry_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_tags');
    }
};
