<?php

namespace Tests\Feature;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;
use Illuminate\Support\Facades\File;

final class CreateQuizFromJsonTest extends TestCase
{

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonPath = base_path('tests/fixtures/quiz_test.json');

        // Ensure fixtures directory exists
        if (!File::exists(dirname($this->jsonPath))) {
            File::makeDirectory(dirname($this->jsonPath), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->jsonPath)) {
            File::delete($this->jsonPath);
        }
        parent::tearDown();
    }

    public function test_it_creates_quiz_from_json_file(): void
    {
        // 1. Create a user
        $user = UserModel::factory()->create([
            'email' => 'quizmaster@example.com',
            'name' => 'Quiz Master'
        ]);

        // 2. Create the JSON file
        $data = [
            'name' => 'General Knowledge Quiz',
            'description' => 'A fun quiz for friends',
            'is_quiz' => true,
            'single_submission' => true,
            'fields' => [
                [
                    'label' => 'What is the capital of France?',
                    'type' => 'text',
                    'required' => true,
                    'correct_answer' => 'Paris',
                    'points' => 10,
                    'order' => 0
                ],
                [
                    'label' => 'Select prime numbers',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['label' => 'Two', 'value' => '2'],
                        ['label' => 'Four', 'value' => '4']
                    ],
                    'correct_answer' => '2',
                    'points' => 5,
                    'order' => 1
                ]
            ]
        ];

        File::put($this->jsonPath, json_encode($data));

        // 3. Run the command
        $this->artisan('quiz:import', [
            'path' => $this->jsonPath,
            '--email' => 'quizmaster@example.com'
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Quiz "General Knowledge Quiz" created successfully');

        // 4. Assert Database
        $this->assertDatabaseHas('forms', [
            'name' => 'General Knowledge Quiz',
            'user_id' => $user->id,
            'is_quiz' => true,
            'single_submission' => true
        ]);

        $form = FormModel::query()->where(
            'name',
            'General Knowledge Quiz'
        )->first();

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'label' => 'What is the capital of France?',
            'correct_answer' => 'Paris',
            'points' => 10
        ]);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $form->id,
            'label' => 'Select prime numbers',
            'type' => 'select',
            'points' => 5
        ]);
    }

    public function test_it_fails_if_file_not_found(): void
    {
        $this->artisan('quiz:import', [
            'path' => 'non_existent.json',
            '--email' => 'test@example.com'
        ])
            ->assertFailed()
            ->expectsOutputToContain('File not found');
    }

    public function test_it_fails_if_user_not_found(): void
    {
        File::put($this->jsonPath, json_encode(['name' => 'test']));

        $this->artisan('quiz:import', [
            'path' => $this->jsonPath,
            '--email' => 'unknown@example.com'
        ])
            ->assertFailed()
            ->expectsOutputToContain('User with email "unknown@example.com" not found');
    }
}
