<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Find;

final readonly class FindFormByIdQuery
{
    public function __construct(
        private string $formId,
    ) {
    }

    public function formId(): string
    {
        return $this->formId;
    }
}
