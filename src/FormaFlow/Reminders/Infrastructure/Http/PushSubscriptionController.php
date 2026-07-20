<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Http;

use FormaFlow\Reminders\Application\ReminderDispatcher;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\PushSubscriptionModel;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\QuizAssignmentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class PushSubscriptionController
{
    public function __construct(private ReminderDispatcher $reminderDispatcher)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys.p256dh' => 'required|string|max:1024',
            'keys.auth' => 'required|string|max:512',
            'content_encoding' => 'sometimes|in:aes128gcm,aesgcm',
        ]);

        $subscription = PushSubscriptionModel::query()->firstOrNew([
            'endpoint' => $validated['endpoint'],
        ]);
        if (!$subscription->exists) {
            $subscription->id = Uuid::generate();
        }
        $subscription->fill([
            'user_id' => $request->user()->id,
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
            'user_agent' => mb_substr((string)$request->userAgent(), 0, 512),
            'last_used_at' => now(),
        ])->save();

        QuizAssignmentModel::query()
            ->with('form')
            ->where('recipient_user_id', $request->user()->id)
            ->whereNull('completed_at')
            ->whereNull('last_notified_at')
            ->each(fn(QuizAssignmentModel $assignment) => $this->reminderDispatcher->dispatch($assignment));

        return response()->json(['id' => $subscription->id], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => 'required|url|max:2048']);
        PushSubscriptionModel::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'public_key' => (string)config('webpush.vapid.public_key'),
        ]);
    }
}
