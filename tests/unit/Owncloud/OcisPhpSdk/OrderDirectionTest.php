<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\OrderDirection;
use PHPUnit\Framework\TestCase;

class OrderDirectionTest extends TestCase
{
    /**
     * @return array<int,array{0:OrderDirection,1:string}>
     */
    public static function validOrderDirections(): array
    {
        return [
            [OrderDirection::ASC, "asc"],
            [OrderDirection::DESC, "desc"],
        ];
    }

    /**
     * @dataProvider validOrderDirections
     */
    public function testOrderDirectionString(OrderDirection $direction, string $directionString): void
    {
        $this->assertSame($directionString, $direction->value);
    }
}
