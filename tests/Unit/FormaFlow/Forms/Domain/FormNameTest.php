<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Domain;

use FormaFlow\Forms\Domain\FormName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormNameTest extends TestCase
{
    public function testCanCreateValidName(): void
    {
        $name = new FormName('Test Form');
        self::assertSame('Test Form', $name->value());
    }

    public function testThrowsOnShortName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FormName('ab');
    }

    public function testThrowsOnLongName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FormName(str_repeat('a', 256));
    }

    public function testEqualsComparison(): void
    {
        $name1 = new FormName('Test Form');
        $name2 = new FormName('Test Form');
        $name3 = new FormName('Other Form');

        self::assertTrue($name1->equals($name2));
        self::assertFalse($name1->equals($name3));
    }

    public function testToString(): void
    {
        $name = new FormName('Test Form');
        self::assertSame('Test Form', (string)$name);
    }
}
