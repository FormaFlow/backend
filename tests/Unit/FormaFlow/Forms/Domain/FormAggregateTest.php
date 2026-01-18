<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Domain;

use FormaFlow\Forms\Domain\Field;
use FormaFlow\Forms\Domain\FieldType;
use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormAggregateTest extends TestCase
{
    public function testCanCreateForm(): void
    {
        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        self::assertSame('123', $form->id()->value());
        self::assertSame('00000000-0000-0000-0000-000000000001', $form->userId());
        self::assertSame('Test Form', $form->name()->value());
        self::assertFalse($form->isPublished());
        self::assertCount(0, $form->fields());
    }

    public function testCanAddField(): void
    {
        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000130',
            label: 'Amount',
            type: new FieldType('number'),
            required: true,
        );

        $form->addField($field);

        self::assertCount(1, $form->fields());
    }

    public function testCanPublishForm(): void
    {
        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000130',
            label: 'Amount',
            type: new FieldType('number'),
        );

        $form->addField($field);
        $form->publish();

        self::assertTrue($form->isPublished());
    }

    public function testThrowsWhenPublishingWithoutFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        $form->publish();
    }

    public function testThrowsWhenPublishingTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000130',
            label: 'Amount',
            type: new FieldType('number'),
        );

        $form->addField($field);
        $form->publish();
        $form->publish();
    }

    public function testRecordsEvents(): void
    {
        $form = new FormAggregate(
            id: new FormId('123'),
            userId: '00000000-0000-0000-0000-000000000001',
            name: new FormName('Test Form'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000130',
            label: 'Amount',
            type: new FieldType('number'),
        );

        $form->addField($field);
        $form->publish();

        $events = $form->pullDomainEvents();

        self::assertCount(2, $events);
    }
}
