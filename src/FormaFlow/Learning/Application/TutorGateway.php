<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Application;

interface TutorGateway
{
    /** @return array{answer: string, suggestions: string[], provider: string} */
    public function explain(array $context, string $message): array;
}
