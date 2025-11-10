<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Domain;

use FormaFlow\Forms\Domain\FieldType;
use PHPUnit\Framework\TestCase;

final class FieldTypeTest extends TestCase
{
    public function testCanCreateValidType(): void
    {
        $type = new FieldType('text');
        self::assertSame('text', $type->value());
    }

    public function testThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FieldType('invalid');
    }

    public function testAllValidTypes(): void
    {
        $validTypes = ['text', 'number', 'date', 'boolean', 'select', 'currency', 'email'];

        foreach ($validTypes as $type) {
            $fieldType = new FieldType($type);
            self::assertSame($type, $fieldType->value());
        }
    }

    public function testEqualsComparison(): void
    {
        $type1 = new FieldType('text');
        $type2 = new FieldType('text');
        $type3 = new FieldType('number');

        self::assertTrue($type1->equals($type2));
        self::assertFalse($type1->equals($type3));
    }
}
