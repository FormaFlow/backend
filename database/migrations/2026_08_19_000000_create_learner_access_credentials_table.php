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
        Schema::create('learner_access_credentials', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('user_id');
            $table->string('login_name', 32);
            $table->string('pin_hash');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id']);
            $table->unique(['workspace_id', 'login_name']);
        });

        $now = now();
        $managedLearners = DB::table('workspace_memberships as wm')
            ->join('users as u', 'u.id', '=', 'wm.user_id')
            ->where('wm.role', 'learner')
            ->where('wm.status', 'active')
            ->whereNotNull('u.login_name')
            ->get(['wm.workspace_id', 'wm.user_id', 'u.login_name', 'u.password']);

        foreach ($managedLearners as $learner) {
            DB::table('learner_access_credentials')->insert([
                'id' => (string)Str::uuid(),
                'workspace_id' => $learner->workspace_id,
                'user_id' => $learner->user_id,
                'login_name' => Str::lower((string)$learner->login_name),
                'pin_hash' => $learner->password,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_access_credentials');
    }
};
