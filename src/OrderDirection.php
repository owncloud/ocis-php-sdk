<?php

namespace Owncloud\OcisPhpSdk;

abstract class OrderDirection
{
    public const ASC = "asc";
    public const DESC = "desc";

    public static function isOrderDirectionValid(?string $direction): bool
    {
        $reflector = new \ReflectionClass('Owncloud\OcisPhpSdk\OrderDirection');
        if (!in_array($direction, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}