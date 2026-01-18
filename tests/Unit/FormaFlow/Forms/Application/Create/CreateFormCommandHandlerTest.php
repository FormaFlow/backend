<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Application\Create;

use FormaFlow\Forms\Application\Create\CreateFormCommand;
use FormaFlow\Forms\Application\Create\CreateFormCommandHandler;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Infrastructure\Persistence\InMemoryFormRepository;
use PHPUnit\Framework\TestCase;

final class CreateFormCommandHandlerTest extends TestCase
{
    public function testCanCreateForm(): void
    {
        $repository = new InMemoryFormRepository();
        $handler = new CreateFormCommandHandler($repository);

        $command = new CreateFormCommand(
            id: '123',
            userId: '00000000-0000-0000-0000-000000000001',
            name: 'Test Form',
            description: 'Test description',
        );

        $handler->handle($command);

        $form = $repository->findById(new FormId('123'));

        self::assertNotNull($form);
        self::assertSame('00000000-0000-0000-0000-000000000001', $form->userId());
        self::assertSame('Test Form', $form->name()->value());
        self::assertSame('Test description', $form->description());
    }
}
