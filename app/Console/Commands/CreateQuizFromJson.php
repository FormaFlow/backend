<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FormaFlow\Forms\Application\Import\ImportFormFromJsonCommand;
use FormaFlow\Forms\Application\Import\ImportFormFromJsonCommandHandler;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Console\Command;
use Throwable;

final class CreateQuizFromJson extends Command
{
    protected $signature = 'quiz:import {path : Path to the JSON file} {--email= : Email of the user to assign the form to}';

    protected $description = 'Create a new quiz form from a JSON file';

    public function handle(ImportFormFromJsonCommandHandler $handler): int
    {
        $path = $this->argument('path');
        $email = $this->option('email');

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

        if ($email) {
            $user = UserModel::query()->where('email', $email)->first();
            if (!$user) {
                $this->error("User with email \"{$email}\" not found.");
                return self::FAILURE;
            }
        } else {
            $user = UserModel::query()->first();
            if (!$user) {
                $this->error('No users found in database. Please create a user first or provide --email.');
                return self::FAILURE;
            }
            $this->info("No email provided. Using user: {$user->email}");
        }

        try {
            if (!isset($data['is_quiz'])) $data['is_quiz'] = true;
            if (!isset($data['published'])) $data['published'] = true;

            $command = new ImportFormFromJsonCommand($data, $user->id);
            $formId = $handler->handle($command);

            $this->info("Quiz \"{$data['name']}\" created successfully for user {$user->name}!");
            $this->info("Form ID: {$formId}");
            
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to create quiz: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
