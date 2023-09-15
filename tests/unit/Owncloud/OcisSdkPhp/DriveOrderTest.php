<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\DriveOrder;
use PHPUnit\Framework\TestCase;

class DriveOrderTest extends TestCase
{
    /**
     * @return array<int,array<int, string|null>>
     */
    public function validDriveTypes(): array
    {
        return [
            [null],
            ["name"],
            ["lastModifiedDateTime"]
        ];
    }

    /**
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void
    {
        $this->assertTrue(DriveOrder::isOrderValid($type));
    }

    public function testInvalidDriveType(): void
    {
        $this->assertFalse(DriveOrder::isOrderValid("some string"));
    }
}
