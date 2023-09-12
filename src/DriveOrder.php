<?php

namespace Owncloud\OcisSdkPhp;

abstract class DriveOrder
{
    public const LASTMODIFIED = "lastModifiedDateTime";
    public const NAME = "name";

    public static function isOrderValid(?string $order): bool
    {
        $reflector = new \ReflectionClass('Owncloud\OcisSdkPhp\DriveOrder');
        if (!in_array($order, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}
