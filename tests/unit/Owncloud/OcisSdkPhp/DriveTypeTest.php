<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\DriveType;
use PHPUnit\Framework\TestCase;

class DriveTypeTest extends TestCase
{
    /**
     * @return array<int,array<int, string|null>>
     */
    public function validDriveTypes(): array
    {
        return [
            [null],
            ["project"],
            ["personal"],
            ["virtual"],
        ];
    }

    /**
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void
    {
        $this->assertTrue(DriveType::isTypeValid($type));
    }

    public function testInvalidDriveType(): void
    {
        $this->assertFalse(DriveType::isTypeValid("some string"));
    }
}
