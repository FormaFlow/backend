<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_categories', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 120);
            $table->string('color', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('payment_plans', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 160);
            $table->string('payee', 160)->nullable();
            $table->string('type', 24);
            $table->string('status', 24)->default('active');
            $table->char('currency', 3)->default('RUB');
            $table->string('schedule_type', 24);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('day_of_month')->nullable();
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->unsignedInteger('total_installments')->nullable();
            $table->decimal('default_nominal_amount', 14, 2)->nullable();
            $table->decimal('default_expected_amount', 14, 2)->nullable();
            $table->decimal('fee_percent', 8, 4)->default(0);
            $table->decimal('fee_fixed', 14, 2)->default(0);
            $table->string('source_type', 48)->nullable();
            $table->string('source_key', 190)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('payment_categories')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'source_type', 'source_key']);
        });

        Schema::create('payment_occurrences', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->date('due_on');
            $table->unsignedInteger('sequence_no')->nullable();
            $table->unsignedInteger('total_count')->nullable();
            $table->string('kind', 24)->default('scheduled');
            $table->decimal('nominal_amount', 14, 2)->nullable();
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->decimal('actual_amount', 14, 2)->nullable();
            $table->string('status', 24)->default('planned');
            $table->timestampTz('paid_at')->nullable();
            $table->string('source_type', 48)->nullable();
            $table->string('source_key', 190)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('payment_plans')->cascadeOnDelete();
            $table->index(['plan_id', 'status', 'due_on']);
            $table->unique(['plan_id', 'due_on', 'kind']);
            $table->unique(['plan_id', 'source_type', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_occurrences');
        Schema::dropIfExists('payment_plans');
        Schema::dropIfExists('payment_categories');
    }
};
