<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Http;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Reminders\Application\ReminderDispatcher;
use FormaFlow\Reminders\Infrastructure\Persistence\Eloquent\QuizAssignmentModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class QuizAssignmentController
{
    public function __construct(private ReminderDispatcher $reminderDispatcher)
    {
    }

    public function index(Request $request, string $formId): JsonResponse
    {
        $form = $this->ownedQuiz($request, $formId);
        if ($form instanceof JsonResponse) {
            return $form;
        }

        $assignments = QuizAssignmentModel::query()
            ->with('recipient:id,name,email')
            ->where('form_id', $formId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['assignments' => $this->serialize($assignments)]);
    }

    public function store(Request $request, string $formId): JsonResponse
    {
        $form = $this->ownedQuiz($request, $formId);
        if ($form instanceof JsonResponse) {
            return $form;
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'required|uuid|exists:users,id',
        ]);
        $userIds = array_values(array_unique($validated['user_ids']));
        $existingRecipients = UserModel::query()->whereIn('id', $userIds)->pluck('id')->all();

        foreach ($existingRecipients as $recipientId) {
            $completed = EntryModel::query()
                ->where('form_id', $formId)
                ->where('user_id', $recipientId)
                ->exists();

            $assignment = QuizAssignmentModel::query()->firstOrNew([
                'form_id' => $formId,
                'recipient_user_id' => $recipientId,
            ]);
            $isNew = !$assignment->exists;
            if ($isNew) {
                $assignment->id = Uuid::generate();
                $assignment->fill([
                    'assigner_user_id' => $request->user()->id,
                    'completed_at' => $completed ? now() : null,
                    'next_reminder_at' => $completed ? null : now(),
                ])->save();

                if (!$completed) {
                    $this->reminderDispatcher->dispatch($assignment);
                }
            } elseif ($completed && $assignment->completed_at === null) {
                $assignment->update(['completed_at' => now(), 'next_reminder_at' => null]);
            }
        }

        $assignments = QuizAssignmentModel::query()
            ->with('recipient:id,name,email')
            ->where('form_id', $formId)
            ->whereIn('recipient_user_id', $existingRecipients)
            ->get();

        return response()->json(
            ['assignments' => $this->serialize($assignments)],
            Response::HTTP_CREATED
        );
    }

    private function ownedQuiz(Request $request, string $formId): FormModel|JsonResponse
    {
        $form = FormModel::query()->find($formId);
        if ($form === null) {
            return response()->json(['message' => 'Form not found'], Response::HTTP_NOT_FOUND);
        }
        if ($form->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        if (!$form->published || !$form->is_quiz) {
            return response()->json(
                ['message' => 'Only published quizzes can be assigned'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $form;
    }

    private function serialize(iterable $assignments): array
    {
        $result = [];
        foreach ($assignments as $assignment) {
            $result[] = [
                'id' => $assignment->id,
                'recipient' => [
                    'id' => $assignment->recipient->id,
                    'name' => $assignment->recipient->name,
                    'email' => $assignment->recipient->email,
                ],
                'last_notified_at' => $assignment->last_notified_at?->format('c'),
                'next_reminder_at' => $assignment->next_reminder_at?->format('c'),
                'completed_at' => $assignment->completed_at?->format('c'),
            ];
        }

        return $result;
    }
}
