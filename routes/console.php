<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:send-due')->everyMinute()->withoutOverlapping();
Schedule::command('learning:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('payments:materialize')->dailyAt('00:15')->withoutOverlapping();
