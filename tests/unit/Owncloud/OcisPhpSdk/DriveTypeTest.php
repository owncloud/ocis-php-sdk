<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\DriveType;
use PHPUnit\Framework\TestCase;

class DriveTypeTest extends TestCase
{
    /**
     * @return array<int,array{0:DriveType,1:string}>     */
    public function validDriveTypes(): array
    {
        return [
            [DriveType::PERSONAL, "personal"],
            [DriveType::PROJECT, "project"],
            [DriveType::VIRTUAL, "virtual"],
        ];
    }

    /**
     * @dataProvider validDriveTypes
     */
    public function testDriveTypeString(DriveType $type, string $driveTypeString): void
    {
        $this->assertEquals($driveTypeString, $type->value);
    }
}
