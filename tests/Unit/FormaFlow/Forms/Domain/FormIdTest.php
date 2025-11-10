<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Domain;

use FormaFlow\Forms\Domain\FormId;
use PHPUnit\Framework\TestCase;

final class FormIdTest extends TestCase
{
    public function testCanCreateValidId(): void
    {
        $id = new FormId('123e4567-e89b-12d3-a456-426614174000');
        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $id->value());
    }

    public function testThrowsOnEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FormId('');
    }

    public function testEqualsComparison(): void
    {
        $id1 = new FormId('123');
        $id2 = new FormId('123');
        $id3 = new FormId('456');

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));
    }
}
