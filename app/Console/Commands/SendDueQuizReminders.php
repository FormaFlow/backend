<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FormaFlow\Reminders\Application\ReminderDispatcher;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\QuizAssignmentModel;
use Illuminate\Console\Command;

final class SendDueQuizReminders extends Command
{
    protected $signature = 'reminders:send-due {--limit=100}';
    protected $description = 'Send due quiz reminders and close completed assignments';

    public function __construct(private readonly ReminderDispatcher $reminderDispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $assignments = QuizAssignmentModel::query()
            ->with('form')
            ->whereNull('completed_at')
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->orderBy('next_reminder_at')
            ->limit(max(1, min(1000, (int)$this->option('limit'))))
            ->get();

        foreach ($assignments as $assignment) {
            $this->reminderDispatcher->dispatch($assignment);
        }

        return self::SUCCESS;
    }
}
