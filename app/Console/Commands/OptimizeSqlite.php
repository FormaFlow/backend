<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OptimizeSqlite extends Command
{
    protected $signature = 'app:optimize-sqlite';
    protected $description = 'Optimize SQLite database for production';

    public function handle(): void
    {
        $this->info('Optimizing SQLite...');

        DB::statement('PRAGMA journal_mode=WAL;');
        DB::statement('PRAGMA synchronous=NORMAL;');
        DB::statement('PRAGMA busy_timeout=5000;');
        DB::statement('PRAGMA foreign_keys=ON;');

        $mode = DB::select('PRAGMA journal_mode;')[0]->journal_mode;

        $this->info("Current Journal Mode: {$mode}");

        if ($mode === 'wal') {
            $this->info('SQLite optimized successfully!');
        } else {
            $this->error('Failed to set WAL mode.');
        }
    }
}
