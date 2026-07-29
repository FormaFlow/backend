<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('form_fields', static function (Blueprint $table): void {
            $table->boolean('sum_values')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', static function (Blueprint $table): void {
            $table->dropColumn('sum_values');
        });
    }
};
