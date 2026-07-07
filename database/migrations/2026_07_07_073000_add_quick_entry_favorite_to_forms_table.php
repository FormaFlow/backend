<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forms', static function (Blueprint $table): void {
            $table->boolean('quick_entry_favorite')->default(false)->after('single_submission');
            $table->index(['user_id', 'quick_entry_favorite']);
        });
    }

    public function down(): void
    {
        Schema::table('forms', static function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'quick_entry_favorite']);
            $table->dropColumn('quick_entry_favorite');
        });
    }
};
