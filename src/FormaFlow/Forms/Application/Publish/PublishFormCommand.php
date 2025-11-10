<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Publish;

final class PublishFormCommand
{
    public function __construct(
        private readonly string $formId,
    ) {}

    public function formId(): string { return $this->formId; }
}
