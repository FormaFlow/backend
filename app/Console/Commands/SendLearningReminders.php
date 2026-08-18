<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FormaFlow\Learning\Application\StudyReminderDispatcher;
use Illuminate\Console\Command;

final class SendLearningReminders extends Command
{
    protected $signature = 'learning:send-reminders';
    protected $description = 'Send scheduled learner and missed-study guardian notifications';

    public function handle(StudyReminderDispatcher $dispatcher): int
    {
        $this->info('Processed notifications: ' . $dispatcher->dispatchDue());
        return self::SUCCESS;
    }
}
