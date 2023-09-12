<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\DriveType;
use PHPUnit\Framework\TestCase;

class DriveTypeTest extends TestCase
{
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
     * @param string|null $type
     * @return void
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void
    {
        $this->assertTrue(DriveType::isTypeValid($type));
    }

    /**
     * @return void
     */
    public function testInvalidDriveType(): void
    {
        $this->assertFalse(DriveType::isTypeValid("some string"));
    }
}
