<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('forms', static function (Blueprint $table): void {
            $table->boolean('timer_enabled')->default(false)->after('is_quiz');
        });
    }

    public function down(): void
    {
        Schema::table('forms', static function (Blueprint $table): void {
            $table->dropColumn('timer_enabled');
        });
    }
};
