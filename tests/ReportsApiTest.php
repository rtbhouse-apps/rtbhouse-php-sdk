<?php
declare(strict_types=1);

namespace RTBHouse\Tests;

use PHPUnit\Framework\TestCase;
use RTBHouse\ReportsApi;

final class ReportsApiTest extends TestCase
{
    public function testCanBeCreatedFromValidEmailAddress(): void
    {
        $this->assertInstanceOf(
            ReportsApi::class,
            ReportsApi::fromString('user@example.com')
        );
    }

    public function testCannotBeCreatedFromInvalidEmailAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReportsApi::fromString('invalid');
    }

    public function testCanBeUsedAsString(): void
    {
        $this->assertEquals(
            'user@example.com',
            ReportsApi::fromString('user@example.com')
        );
    }
}
