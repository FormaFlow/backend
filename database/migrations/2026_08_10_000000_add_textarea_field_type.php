<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const array TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'boolean',
        'select',
        'currency',
        'email',
    ];

    public function up(): void
    {
        $this->changeTypes(self::TYPES);
    }

    public function down(): void
    {
        $this->changeTypes(array_values(array_filter(
            self::TYPES,
            static fn(string $type): bool => $type !== 'textarea',
        )));
    }

    private function changeTypes(array $types): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $quotedTypes = implode(', ', array_map(
                static fn(string $type): string => DB::getPdo()->quote($type),
                $types,
            ));
            DB::statement('ALTER TABLE form_fields DROP CONSTRAINT IF EXISTS form_fields_type_check');
            DB::statement(
                "ALTER TABLE form_fields ADD CONSTRAINT form_fields_type_check CHECK (type IN ({$quotedTypes}))"
            );
            return;
        }

        Schema::table('form_fields', static function (Blueprint $table) use ($types): void {
            $table->enum('type', $types)->change();
        });
    }
};
