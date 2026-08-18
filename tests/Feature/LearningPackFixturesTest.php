<?php

declare(strict_types=1);

namespace Tests\Feature;

use FormaFlow\Learning\Application\LearningPackService;
use FormaFlow\Learning\Domain\QuestionGrader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LearningPackFixturesTest extends TestCase
{
    #[DataProvider('packs')]
    public function test_built_in_pack_is_valid_owned_original_content(string $file, int $questionCount): void
    {
        $payload = json_decode((string)file_get_contents(resource_path('learning-packs/' . $file)), true, 512, JSON_THROW_ON_ERROR);
        $validated = $this->app->make(LearningPackService::class)->validate($payload);
        self::assertSame('owned', $validated['pack']['source']['type']);
        self::assertSame('publishable', $validated['pack']['source']['usage_scope']);
        self::assertSame($questionCount, count($validated['questions']));
        self::assertSame($questionCount, count(array_unique(array_column($validated['questions'], 'external_id'))));

        $grader = $this->app->make(QuestionGrader::class);
        foreach ($validated['questions'] as $question) {
            $answer = $question['answer_config']['accepted'][0] ?? $question['answer_config']['correct'] ?? null;
            if ($question['type'] !== 'multiple_choice') {
                $answer = is_array($answer) ? $answer[0] : $answer;
            }
            self::assertNotNull($answer, $question['external_id'] . ' has no answer key');
            self::assertTrue(
                $grader->isCorrect($question['type'], $question['answer_config'], $answer),
                $question['external_id'] . ' answer key cannot be graded',
            );

            if (in_array($question['type'], ['single_choice', 'multiple_choice'], true)) {
                $optionValues = array_column($question['options'] ?? [], 'value');
                foreach ($question['answer_config']['correct'] as $correct) {
                    self::assertContains($correct, $optionValues, $question['external_id'] . ' answer is absent from options');
                }
            }
        }
    }

    public static function packs(): array
    {
        return [
            ['grade-1-math-foundation-100.ru.json', 100],
            ['grade-4-math-diagnostic.ru.json', 30],
            ['grade-4-russian-diagnostic.ru.json', 25],
            ['grade-4-math-advanced.ru.json', 30],
            ['grade-4-russian-advanced.ru.json', 25],
            ['grade-9-math-diagnostic.ru.json', 30],
        ];
    }
}
