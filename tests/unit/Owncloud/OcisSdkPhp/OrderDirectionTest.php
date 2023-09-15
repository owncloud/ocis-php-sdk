<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\OrderDirection;
use PHPUnit\Framework\TestCase;

class OrderDirectionTest extends TestCase
{
    /**
     * @return array<int,array<int, string|null>>
     */
    public function validDriveTypes(): array
    {
        return [
            [null],
            ["asc"],
            ["desc"]
        ];
    }

    /**
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void
    {
        $this->assertTrue(OrderDirection::isOrderDirectionValid($type));
    }

    public function testInvalidDriveType(): void
    {
        $this->assertFalse(OrderDirection::isOrderDirectionValid("some string"));
    }
}
