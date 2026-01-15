<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

final class QuizImportAndSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonPath = base_path('tests/fixtures/quiz_import_test.json');
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

    public function test_math_and_logic_quizzes_import_and_scoring(): void
    {
        // 1. Setup User
        $user = UserModel::factory()->create(['email' => 'admin@example.com']);

        // 2. Prepare Math Quiz JSON
        $mathQuizData = [
            'name' => 'Math Challenge',
            'description' => 'Test your math skills',
            'is_quiz' => true,
            'fields' => [
                [
                    'label' => '2 + 2 * 2',
                    'type' => 'number',
                    'required' => true,
                    'correct_answer' => '6',
                    'points' => 10,
                    'order' => 0
                ],
                [
                    'label' => 'Square root of 144',
                    'type' => 'text',
                    'required' => true,
                    'correct_answer' => '12',
                    'points' => 20,
                    'order' => 1
                ]
            ]
        ];

        File::put($this->jsonPath, json_encode($mathQuizData));

        // 3. Import Math Quiz
        $this->artisan('quiz:import', [
            'path' => $this->jsonPath,
            '--email' => 'admin@example.com'
        ])->assertSuccessful();

        /** @var FormModel $mathForm */
        $mathForm = FormModel::query()->where('name', 'Math Challenge')->first();
        $mathForm->update(['published' => true]);

        $q1 = $mathForm->fields()->where('label', '2 + 2 * 2')->first()->id;
        $q2 = $mathForm->fields()->where('label', 'Square root of 144')->first()->id;

        // 4. Submit Math Quiz - Correct Answers
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/entries', [
                'form_id' => $mathForm->id,
                'data' => [
                    $q1 => '6',
                    $q2 => '12'
                ]
            ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('score', 30);

        // 5. Submit Math Quiz - Partially Correct
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/entries', [
                'form_id' => $mathForm->id,
                'data' => [
                    $q1 => '8',
                    $q2 => '12'
                ]
            ]);

        $response->assertJsonPath('score', 20);

        // 6. Prepare Choice Quiz JSON
        $logicQuizData = [
            'name' => 'Logic Quiz',
            'is_quiz' => true,
            'fields' => [
                [
                    'label' => 'Which planet is known as the Red Planet?',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['label' => 'Venus', 'value' => 'venus'],
                        ['label' => 'Mars', 'value' => 'mars'],
                        ['label' => 'Jupiter', 'value' => 'jupiter']
                    ],
                    'correct_answer' => 'mars',
                    'points' => 5,
                    'order' => 0
                ]
            ]
        ];

        File::put($this->jsonPath, json_encode($logicQuizData));

        // 7. Import Choice Quiz
        $this->artisan('quiz:import', [
            'path' => $this->jsonPath,
            '--email' => 'admin@example.com'
        ])->assertSuccessful();

        /** @var FormModel $logicForm */
        $logicForm = FormModel::query()->where('name', 'Logic Quiz')->first();
        $logicForm->update(['published' => true]);

        $choice1 = $logicForm->fields()->where('label', 'Which planet is known as the Red Planet?')->first()->id;

        // 8. Submit Choice Quiz - Correct
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/entries', [
                'form_id' => $logicForm->id,
                'data' => [
                    $choice1 => 'mars'
                ]
            ]);

        $response->assertJsonPath('score', 5);

        // 9. Submit Choice Quiz - Incorrect
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/entries', [
                'form_id' => $logicForm->id,
                'data' => [
                    $choice1 => 'venus'
                ]
            ]);

        $response->assertJsonPath('score', 0);
    }
}
