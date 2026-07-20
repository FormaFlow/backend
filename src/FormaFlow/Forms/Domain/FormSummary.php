<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Domain;

use DateTimeInterface;

final readonly class FormSummary
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $name,
        public ?string $description,
        public bool $published,
        public bool $isQuiz,
        public bool $singleSubmission,
        public bool $quickEntryFavorite,
        public int $fieldsCount,
        public int $entriesCount,
        public DateTimeInterface $createdAt,
        public DateTimeInterface $updatedAt,
    ) {
    }
}
