<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Update;

final readonly class UpdateFormCommand
{
    public function __construct(
        public string $id,
        public string $userId,
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $isQuiz = null,
        public ?bool $singleSubmission = null,
    ) {
    }
}
