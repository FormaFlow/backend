<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Application;

use FormaFlow\Reminders\Application\PushGateway;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\PushSubscriptionModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class LearningAssignmentNotifier
{
    public function __construct(private PushGateway $pushGateway)
    {
    }

    public function notifyLatestPending(string $learnerUserId): bool
    {
        $assignmentId = DB::table('learning_assignments')
            ->where('learner_user_id', $learnerUserId)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderByDesc('assigned_at')
            ->value('id');

        return is_string($assignmentId) && $this->notify($assignmentId);
    }

    public function notify(string $assignmentId): bool
    {
        $assignment = DB::table('learning_assignments as assignment')
            ->join('learning_assessments as assessment', 'assessment.id', '=', 'assignment.assessment_id')
            ->join('forms as form', 'form.id', '=', 'assessment.form_id')
            ->where('assignment.id', $assignmentId)
            ->select(['assignment.id', 'assignment.learner_user_id', 'form.name as title'])
            ->first();
        if ($assignment === null) {
            return false;
        }

        $subscriptions = PushSubscriptionModel::query()
            ->where('user_id', $assignment->learner_user_id)
            ->get();
        if ($subscriptions->isEmpty()) {
            return false;
        }

        try {
            $expired = $this->pushGateway->send(
                $subscriptions->map(static fn(PushSubscriptionModel $subscription): array => [
                    'endpoint' => $subscription->endpoint,
                    'public_key' => $subscription->public_key,
                    'auth_token' => $subscription->auth_token,
                    'content_encoding' => $subscription->content_encoding,
                ])->all(),
                [
                    'title' => 'Новое задание: ' . $assignment->title,
                    'body' => 'Открой FormaFlow, чтобы начать.',
                    'url' => '/learn/assignments/' . $assignment->id,
                    'tag' => 'learning-assignment-' . $assignment->id,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Learning assignment push failed', [
                'assignment_id' => $assignmentId,
                'reason' => $exception->getMessage(),
            ]);
            return false;
        }

        if ($expired !== []) {
            PushSubscriptionModel::query()->whereIn('endpoint', $expired)->delete();
        }

        return $subscriptions->count() > count(array_unique($expired));
    }
}
