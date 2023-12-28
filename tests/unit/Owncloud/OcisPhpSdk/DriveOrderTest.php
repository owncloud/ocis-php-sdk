<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\DriveOrder;
use PHPUnit\Framework\TestCase;

class DriveOrderTest extends TestCase
{
    /**
     * @return array<int,array{0:DriveOrder,1:string}>
     */
    public static function validDriveOrders(): array
    {
        return [
            [DriveOrder::LASTMODIFIED, "lastModifiedDateTime"],
            [DriveOrder::NAME, "name"],
        ];
    }

    /**
     * @dataProvider validDriveOrders
     */
    public function testDriveOrderString(DriveOrder $order, string $driveOrderString): void
    {
        $this->assertEquals($driveOrderString, $order->value);
    }
}
