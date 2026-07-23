<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entries', static function (Blueprint $table): void {
            $table->index(['form_id', 'user_id', 'created_at'], 'entries_form_user_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('entries', static function (Blueprint $table): void {
            $table->dropIndex('entries_form_user_created_at_index');
        });
    }
};
