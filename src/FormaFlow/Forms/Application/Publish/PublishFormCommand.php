<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Publish;

final readonly class PublishFormCommand
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
