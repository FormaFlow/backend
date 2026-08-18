<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Learning;

use FormaFlow\Learning\Domain\QuestionGrader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuestionGraderTest extends TestCase
{
    #[DataProvider('answers')]
    public function test_grades_supported_question_types(string $type, array $config, mixed $answer, bool $expected): void
    {
        self::assertSame($expected, (new QuestionGrader())->isCorrect($type, $config, $answer));
    }

    public static function answers(): array
    {
        return [
            'single' => ['single_choice', ['correct' => ['b']], 'b', true],
            'multiple order independent' => ['multiple_choice', ['correct' => ['a', 'c']], ['c', 'a'], true],
            'multiple incomplete' => ['multiple_choice', ['correct' => ['a', 'c']], ['a'], false],
            'text normalized' => ['short_text', ['accepted' => ['  Медведь ']], 'медведь', true],
            'number comma' => ['number', ['accepted' => ['2.5']], '2,5', true],
            'number tolerance' => ['number', ['accepted' => ['3.14'], 'tolerance' => 0.01], 3.145, true],
            'boolean' => ['boolean', ['correct' => [true]], true, true],
        ];
    }
}
