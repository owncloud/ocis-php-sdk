<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\OrderDirection;
use PHPUnit\Framework\TestCase;

class OrderDirectionTest extends TestCase
{

    public function validDriveTypes(): array {
        return [
            [null],
            ["asc"],
            ["desc"]
        ];
    }

    /**
     * @param string|null $type
     * @return void
     * @dataProvider validDriveTypes
     */
    public function testValidDriveType(?string $type): void {
        $this->assertTrue(OrderDirection::isOrderDirectionValid($type));
    }

    /**
     * @return void
     */
    public function testInvalidDriveType(): void {
        $this->assertFalse(OrderDirection::isOrderDirectionValid("some string"));
    }
}
