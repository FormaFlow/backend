<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class QuizTest extends TestCase
{

    protected ?UserModel $user = null;
    protected string $baseUrl = '/api/v1/entries';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_quiz_entry_calculates_score(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Quiz Form',
            'is_quiz' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Question 1',
                'type' => 'text',
                'required' => true,
                'correct_answer' => 'Answer 1',
                'points' => 10,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000141',
                'form_id' => $form->id,
                'label' => 'Question 2',
                'type' => 'number',
                'required' => true,
                'correct_answer' => '42',
                'points' => 20,
                'order' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => [
                '00000000-0000-0000-0000-000000000140' => 'Answer 1', // Correct (10)
                '00000000-0000-0000-0000-000000000141' => 50,         // Incorrect (0)
            ],
        ];

        $response = $this
            ->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'score' => 10,
                'quiz_results' => [
                    [
                        'label' => 'Question 1',
                        'is_correct' => true,
                        'points' => 10,
                    ],
                    [
                        'label' => 'Question 2',
                        'is_correct' => false,
                        'points' => 20,
                    ]
                ]
            ]);

        $entry = EntryModel::query()->where('form_id', $form->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals(10, $entry->score);
    }

    public function test_quiz_entry_calculates_perfect_score(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Quiz Form',
            'is_quiz' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Question 1',
                'type' => 'text',
                'required' => true,
                'correct_answer' => 'Answer 1',
                'points' => 10,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => [
                '00000000-0000-0000-0000-000000000140' => 'Answer 1',
            ],
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData)
            ->assertStatus(Response::HTTP_CREATED);

        $entry = EntryModel::query()->where('form_id', $form->id)->first();
        $this->assertEquals(10, $entry->score);
    }

    public function test_entry_saves_duration(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Timed Form',
            'is_quiz' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Question 1',
                'type' => 'text',
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => ['00000000-0000-0000-0000-000000000140' => 'val'],
            'duration' => 120, // 2 minutes
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData)
            ->assertStatus(Response::HTTP_CREATED);

        $entry = EntryModel::query()->where('form_id', $form->id)->first();
        $this->assertEquals(120, $entry->duration);
    }

    public function test_single_submission_prevents_duplicate(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Single Submission Form',
            'single_submission' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Q1',
                'type' => 'text',
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => ['00000000-0000-0000-0000-000000000140' => 'first'],
        ];

        // First submission
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData)
            ->assertStatus(Response::HTTP_CREATED);

        // Second submission
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData);

        // Check that only 1 entry exists
        $this->assertEquals(1, EntryModel::query()->where('form_id', $form->id)->count());
    }

    public function test_quiz_scoring_is_robust_to_casing_and_whitespace(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Robust Quiz',
            'is_quiz' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Question 1',
                'type' => 'text',
                'correct_answer' => 'Paris',
                'points' => 10,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => [
                '00000000-0000-0000-0000-000000000140' => '  pArIs  ', // Mixed case and whitespace
            ],
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData)
            ->assertStatus(Response::HTTP_CREATED);

        $entry = EntryModel::query()->where('form_id', $form->id)->first();
        $this->assertEquals(10, $entry->score);
    }

    public function test_quiz_scoring_supports_cyrillic(): void
    {
        $form = FormModel::factory()->forUser($this->user)->published()->create([
            'name' => 'Cyrillic Quiz',
            'is_quiz' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => '00000000-0000-0000-0000-000000000140',
                'form_id' => $form->id,
                'label' => 'Question 1',
                'type' => 'text',
                'correct_answer' => 'Медведь',
                'points' => 10,
                'order' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $entryData = [
            'form_id' => $form->id,
            'data' => [
                '00000000-0000-0000-0000-000000000140' => 'медведь', // lowercase
            ],
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->baseUrl, $entryData)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJson([
                'score' => 10,
                'quiz_results' => [
                    [
                        'label' => 'Question 1',
                        'is_correct' => true,
                    ]
                ]
            ]);

        $entry = EntryModel::query()->where('form_id', $form->id)->first();
        $this->assertEquals(10, $entry->score);
    }
}
