<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('form_fields', static function (Blueprint $table): void {
            $table->string('trend_direction', 20)->default('neutral')->after('sum_values');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', static function (Blueprint $table): void {
            $table->dropColumn('trend_direction');
        });
    }
};
