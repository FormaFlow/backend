<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Application\Create;

use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Domain\FormRepository;

final readonly class CreateFormCommandHandler
{
    public function __construct(
        private FormRepository $repository,
    ) {
    }

    public function handle(CreateFormCommand $command): void
    {
        $form = new FormAggregate(
            id: new FormId($command->id()),
            userId: $command->userId(),
            name: new FormName($command->name()),
            description: $command->description(),
        );

        $this->repository->save($form);
    }
}
