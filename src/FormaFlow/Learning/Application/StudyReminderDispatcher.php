<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Application;

use Carbon\CarbonImmutable;
use FormaFlow\Reminders\Application\PushGateway;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\PushSubscriptionModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class StudyReminderDispatcher
{
    public function __construct(private PushGateway $pushGateway)
    {
    }

    public function dispatchDue(): int
    {
        $processed = 0;
        foreach (DB::table('study_schedules')->where('enabled', true)->get() as $schedule) {
            $localNow = CarbonImmutable::now($schedule->timezone);
            $weekdays = is_string($schedule->weekdays) ? json_decode($schedule->weekdays, true, 512, JSON_THROW_ON_ERROR) : $schedule->weekdays;
            if (!in_array($localNow->dayOfWeekIso, $weekdays, true) || !$this->hasOpenWork($schedule)) {
                continue;
            }
            $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $localNow->toDateString() . ' ' . substr((string)$schedule->daily_time, 0, 5), $schedule->timezone);
            if ($localNow->greaterThanOrEqualTo($start)) {
                $processed += $this->notifyOnce($schedule, 'learner_due', $localNow->toDateString(), $schedule->learner_user_id, [
                    'title' => 'Время небольшого занятия',
                    'body' => 'Твой план и вопросы для повторения уже готовы.',
                    'url' => '/learn',
                    'tag' => 'learning-due-' . $schedule->id . '-' . $localNow->toDateString(),
                ]);
            }
            if (
                $localNow->greaterThanOrEqualTo($start->addMinutes((int)$schedule->guardian_delay_minutes))
                && !$this->completedSince($schedule, $start)
            ) {
                $processed += $this->notifyOnce($schedule, 'guardian_missed', $localNow->toDateString(), $schedule->guardian_user_id, [
                    'title' => 'Учебный план ещё не выполнен',
                    'body' => 'Напомните ребёнку о сегодняшнем коротком занятии.',
                    'url' => '/admin',
                    'tag' => 'learning-missed-' . $schedule->id . '-' . $localNow->toDateString(),
                ]);
            }
        }
        return $processed;
    }

    private function hasOpenWork(object $schedule): bool
    {
        return DB::table('learning_assignments')->where([
            'workspace_id' => $schedule->workspace_id, 'learner_user_id' => $schedule->learner_user_id,
        ])->whereIn('status', ['assigned', 'in_progress'])->exists()
            || DB::table('learning_review_items')->where([
                'workspace_id' => $schedule->workspace_id, 'learner_user_id' => $schedule->learner_user_id,
            ])->whereIn('status', ['due', 'scheduled'])->where('next_review_at', '<=', now())->exists();
    }

    private function completedSince(object $schedule, CarbonImmutable $start): bool
    {
        return DB::table('learning_attempts')->where([
            'workspace_id' => $schedule->workspace_id, 'learner_user_id' => $schedule->learner_user_id, 'status' => 'completed',
        ])->where('completed_at', '>=', $start->utc())->exists();
    }

    private function notifyOnce(object $schedule, string $kind, string $date, string $userId, array $payload): int
    {
        if (
            DB::table('learning_notification_log')->where([
            'schedule_id' => $schedule->id, 'kind' => $kind, 'local_date' => $date,
            ])->exists()
        ) {
            return 0;
        }
        $subscriptions = PushSubscriptionModel::query()->where('user_id', $userId)->get();
        $status = 'no_subscription';
        if ($subscriptions->isNotEmpty()) {
            $expired = $this->pushGateway->send($subscriptions->map(static fn($subscription): array => [
                'endpoint' => $subscription->endpoint,
                'public_key' => $subscription->public_key,
                'auth_token' => $subscription->auth_token,
                'content_encoding' => $subscription->content_encoding,
            ])->all(), $payload);
            if ($expired !== []) {
                PushSubscriptionModel::query()->whereIn('endpoint', $expired)->delete();
            }
            $status = $subscriptions->count() > count(array_unique($expired)) ? 'sent' : 'expired';
        }
        DB::table('learning_notification_log')->insert([
            'id' => (string)Str::uuid(), 'schedule_id' => $schedule->id, 'kind' => $kind,
            'local_date' => $date, 'status' => $status, 'processed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return 1;
    }
}
