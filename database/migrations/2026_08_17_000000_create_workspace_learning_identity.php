<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('login_name')->nullable()->after('email');
            $table->string('account_type', 32)->default('adult')->after('login_name');
        });

        Schema::create('workspaces', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 32)->default('family');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->timestamps();
        });

        Schema::create('workspace_memberships', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->string('role', 32);
            $table->string('status', 32)->default('active');
            $table->uuid('managed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('managed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'role', 'status']);
        });

        Schema::create('workspace_invitations', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->uuid('invited_by_user_id');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['workspace_id', 'email']);
        });

        Schema::create('workspace_modules', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('module_key', 64);
            $table->boolean('enabled')->default(true);
            $table->jsonb('config')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'module_key']);
        });

        Schema::create('learner_profiles', static function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->unsignedSmallInteger('target_grade');
            $table->string('timezone', 64)->default('Europe/Moscow');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('forms', static function (Blueprint $table): void {
            $table->uuid('workspace_id')->nullable()->after('user_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            $table->index(['workspace_id', 'is_quiz']);
        });

        Schema::table('entries', static function (Blueprint $table): void {
            $table->string('public_share_token_hash', 64)->nullable()->unique();
            $table->timestamp('public_share_expires_at')->nullable();
        });

        $now = now();
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'timezone']) as $user) {
            $workspaceId = (string)Str::uuid();
            DB::table('workspaces')->insert([
                'id' => $workspaceId,
                'name' => $user->name . ' workspace',
                'slug' => 'personal-' . str_replace('-', '', (string)$user->id),
                'type' => 'family',
                'timezone' => $user->timezone ?: 'Europe/Moscow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('workspace_memberships')->insert([
                'id' => (string)Str::uuid(),
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach (['learning', 'reminders', 'gamification', 'tutor'] as $module) {
                DB::table('workspace_modules')->insert([
                    'id' => (string)Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'module_key' => $module,
                    'enabled' => $module !== 'tutor',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('forms')->where('user_id', $user->id)->update(['workspace_id' => $workspaceId]);
        }
    }

    public function down(): void
    {
        Schema::table('entries', static function (Blueprint $table): void {
            $table->dropColumn(['public_share_token_hash', 'public_share_expires_at']);
        });
        Schema::table('forms', static function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
        Schema::dropIfExists('learner_profiles');
        Schema::dropIfExists('workspace_modules');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('workspaces');
        DB::table('users')->where('account_type', 'managed_learner')->delete();
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['login_name', 'account_type']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
