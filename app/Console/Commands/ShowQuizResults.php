<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ShowQuizResults extends Command
{
    protected $signature = 'quiz:results';

    protected $description = 'Show results and answers for a selected quiz form';

    public function handle(): void
    {
        // 1. Find Quiz Forms
        $forms = FormModel::query()
            ->where('is_quiz', true)
            ->get();

        if ($forms->isEmpty()) {
            $this->warn('No quiz forms found.');
            return;
        }

        // 2. Ask user to select a form
        $formOptions = $forms->pluck('name')->toArray();

        $selectedFormName = $this->choice(
            'Select a quiz form to view results:',
            $formOptions
        );

        $form = $forms->firstWhere('name', $selectedFormName);

        if (!$form) {
            $this->error('Form not found.');
            return;
        }

        $form->load('fields');

        $this->info("Loading results for: {$form->name}");

        // 3. Fetch Entries
        $entries = EntryModel::query()
            ->where('form_id', $form->id)
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No entries found for this form.');
            return;
        }

        // 4. Fetch Users
        $userIds = $entries->pluck('user_id')->unique();
        $users = UserModel::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        // 5. Build Table
        // Headers
        $headers = ['User', 'Score', 'Time', 'Date'];
        $sortedFields = $form->fields->sortBy('order');

        foreach ($sortedFields as $field) {
            $headers[] = Str::limit($field->label, 20);
        }

        // Rows
        $rows = [];
        foreach ($entries as $entry) {
            $row = [];

            // User
            $userName = $users[$entry->user_id] ?? 'Unknown User';
            $row[] = $userName;

            // Score
            $row[] = $entry->score ?? 0;

            // Time
            $duration = $entry->duration ? gmdate("H:i:s", (int)$entry->duration) : '-';
            $row[] = $duration;

            // Date
            $row[] = $entry->created_at->format('Y-m-d H:i');

            // Fields
            foreach ($sortedFields as $field) {
                $answer = $entry->data[$field->id] ?? '-';
                $correctAnswer = $field->correct_answer;

                $display = (string)$answer;

                if ($form->is_quiz && $correctAnswer !== null && $answer !== '-') {
                    // Check correctness
                    $isCorrect = false;
                    $trimmedAnswer = is_string($answer) ? trim((string)$answer) : $answer;
                    $trimmedCorrect = is_string($correctAnswer) ? trim((string)$correctAnswer) : $correctAnswer;

                    if (is_string($trimmedAnswer) && is_string($trimmedCorrect)) {
                        $isCorrect = mb_strtolower($trimmedAnswer) === mb_strtolower($trimmedCorrect);
                    } else {
                        $isCorrect = $trimmedAnswer == $trimmedCorrect;
                    }

                    if ($isCorrect) {
                        $display .= " (✔)";
                    } else {
                        $display .= " (✘)";
                    }
                }

                $row[] = $display;
            }

            $rows[] = $row;
        }

        $this->table($headers, $rows);
    }
}
