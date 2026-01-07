<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormFieldModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class CreateQuizFromJson extends Command
{
    protected $signature = 'quiz:import {path : Path to the JSON file} {--email= : Email of the user to assign the form to}';

    protected $description = 'Create a new quiz form from a JSON file';

    public function handle(): int
    {
        $path = $this->argument('path');
        $email = $this->option('email');

        // 1. Validate File
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $jsonContent = file_get_contents($path);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file: ' . json_last_error_msg());
            return self::FAILURE;
        }

        // 2. Find User
        if ($email) {
            $user = UserModel::query()->where('email', $email)->first();
            if (!$user) {
                $this->error("User with email \"{$email}\" not found.");
                return self::FAILURE;
            }
        } else {
            // Default to first user if no email provided (convenience for dev)
            $user = UserModel::query()->first();
            if (!$user) {
                $this->error('No users found in database. Please create a user first or provide --email.');
                return self::FAILURE;
            }
            $this->info("No email provided. Using user: {$user->email}");
        }

        // 3. Create Form and Fields Transactionally
        try {
            DB::transaction(function () use ($user, $data) {
                $formId = (string)Str::uuid();

                $form = FormModel::query()->create([
                    'id' => $formId,
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'published' => $data['published'] ?? true, // Default to published for convenience
                    'is_quiz' => $data['is_quiz'] ?? true,
                    'single_submission' => $data['single_submission'] ?? false,
                ]);

                $this->info("Form created with ID: {$form->id}");

                if (isset($data['fields']) && is_array($data['fields'])) {
                    foreach ($data['fields'] as $index => $fieldData) {
                        FormFieldModel::query()->create([
                            'id' => (string)Str::uuid(),
                            'form_id' => $formId,
                            'label' => $fieldData['label'],
                            'type' => $fieldData['type'] ?? 'text',
                            'required' => $fieldData['required'] ?? false,
                            'options' => $fieldData['options'] ?? null, // Eloquent casts array to json
                            'unit' => $fieldData['unit'] ?? null,
                            'category' => $fieldData['category'] ?? null,
                            'order' => $fieldData['order'] ?? $index,
                            'correct_answer' => $fieldData['correct_answer'] ?? null,
                            'points' => $fieldData['points'] ?? 0,
                        ]);
                    }
                    $this->info("Added " . count($data['fields']) . " fields.");
                }
            });

            $this->info("Quiz \"{$data['name']}\" created successfully for user {$user->name}!");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to create quiz: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
