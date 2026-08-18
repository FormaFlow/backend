<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Tutor;

use FormaFlow\Learning\Application\TutorGateway;

final class MockTutorGateway implements TutorGateway
{
    public function explain(array $context, string $message): array
    {
        $question = $context['question'];
        $explanation = trim((string)($question['explanation'] ?? ''));
        $topic = $question['topic'] ?? 'эту тему';
        $answer = $explanation !== ''
            ? "Давай разберём по шагам. {$explanation} Попробуй теперь объяснить решение своими словами."
            : "Давай не угадывать ответ, а разберём {$topic}. Сначала назови, что известно в условии, затем выбери одно действие и проверь результат.";

        return [
            'answer' => $answer,
            'suggestions' => ['Покажи первый шаг', 'Дай похожий пример', 'Проверь моё объяснение'],
            'provider' => 'mock',
        ];
    }
}
