<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Application;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\PushSubscriptionModel;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\QuizAssignmentModel;

final readonly class ReminderDispatcher
{
    public function __construct(private PushGateway $pushGateway)
    {
    }

    public function dispatch(QuizAssignmentModel $assignment): void
    {
        if ($assignment->completed_at !== null) {
            return;
        }

        $completed = EntryModel::query()
            ->where('form_id', $assignment->form_id)
            ->where('user_id', $assignment->recipient_user_id)
            ->exists();

        if ($completed) {
            $assignment->update(['completed_at' => now(), 'next_reminder_at' => null]);

            return;
        }

        $assignment->loadMissing('form');
        $subscriptions = PushSubscriptionModel::query()
            ->where('user_id', $assignment->recipient_user_id)
            ->get();
        if ($subscriptions->isEmpty()) {
            $interval = $assignment->form->reminder_interval_minutes;
            $assignment->update([
                'next_reminder_at' => $interval !== null ? now()->addMinutes((int)$interval) : null,
            ]);

            return;
        }

        $expiredEndpoints = $this->pushGateway->send(
            $subscriptions->map(static fn(PushSubscriptionModel $subscription): array => [
                'endpoint' => $subscription->endpoint,
                'public_key' => $subscription->public_key,
                'auth_token' => $subscription->auth_token,
                'content_encoding' => $subscription->content_encoding,
            ])->all(),
            [
                'title' => $assignment->last_notified_at === null
                    ? 'Новый тест: ' . $assignment->form->name
                    : 'Напоминание о тесте: ' . $assignment->form->name,
                'body' => 'Пройдите назначенный тест.',
                'url' => '/entries/create?form_id=' . $assignment->form_id,
                'tag' => 'quiz-assignment-' . $assignment->id,
            ],
        );

        if ($expiredEndpoints !== []) {
            PushSubscriptionModel::query()->whereIn('endpoint', $expiredEndpoints)->delete();
        }

        $interval = $assignment->form->reminder_interval_minutes;
        $deliveredToActiveSubscription = $subscriptions->count() > count(array_unique($expiredEndpoints));
        $assignment->update([
            'last_notified_at' => $deliveredToActiveSubscription ? now() : $assignment->last_notified_at,
            'next_reminder_at' => $interval !== null ? now()->addMinutes((int)$interval) : null,
        ]);
    }
}
