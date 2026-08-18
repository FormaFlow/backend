<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Domain;

final class QuestionGrader
{
    public function isCorrect(string $type, array $config, mixed $answer): bool
    {
        return match ($type) {
            'single_choice' => $this->single($config, $answer),
            'multiple_choice' => $this->multiple($config, $answer),
            'short_text' => $this->text($config, $answer),
            'number' => $this->number($config, $answer),
            'boolean' => $this->boolean($config, $answer),
            default => false,
        };
    }

    private function single(array $config, mixed $answer): bool
    {
        $correct = $config['correct'][0] ?? null;
        return $correct !== null && (string)$correct === (string)$answer;
    }

    private function multiple(array $config, mixed $answer): bool
    {
        if (!is_array($answer)) {
            return false;
        }
        $expected = array_map('strval', $config['correct'] ?? []);
        $actual = array_map('strval', $answer);
        sort($expected);
        sort($actual);
        return $expected !== [] && $expected === $actual;
    }

    private function text(array $config, mixed $answer): bool
    {
        if (!is_scalar($answer)) {
            return false;
        }
        $actual = $this->normalizeText((string)$answer);
        foreach ($config['accepted'] ?? [] as $accepted) {
            if ($actual === $this->normalizeText((string)$accepted)) {
                return true;
            }
        }
        return false;
    }

    private function number(array $config, mixed $answer): bool
    {
        if (!is_numeric(str_replace(',', '.', (string)$answer))) {
            return false;
        }
        $actual = (float)str_replace(',', '.', (string)$answer);
        $tolerance = (float)($config['tolerance'] ?? 0.0);
        foreach ($config['accepted'] ?? [] as $accepted) {
            $expected = str_replace(',', '.', (string)$accepted);
            if (is_numeric($expected) && abs($actual - (float)$expected) <= $tolerance + PHP_FLOAT_EPSILON) {
                return true;
            }
        }
        return false;
    }

    private function boolean(array $config, mixed $answer): bool
    {
        $expected = $config['correct'][0] ?? null;
        if (!is_bool($expected) || !is_bool($answer)) {
            return false;
        }
        return $expected === $answer;
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
