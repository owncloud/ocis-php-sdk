<?php

namespace Owncloud\OcisSdkPhp;
require_once(__DIR__ . '/../../../src/ocis.php');

use DriveOrder;
use PHPUnit\Framework\TestCase;

class DriveOrderTest extends TestCase
{

    public function validDriveTypes(): array {
        return [
            [null],
            ["name"],
            ["lastModifiedDateTime"]
        ];
    }

    /**
     * @param string|null $type
     * @return void
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void {
        $this->assertTrue(DriveOrder::isOrderValid($type));
    }

    /**
     * @return void
     */
    public function testInvalidDriveType(): void {
        $this->assertFalse(DriveOrder::isOrderValid("some string"));
    }
}
