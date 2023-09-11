<?php

namespace Owncloud\OcisSdkPhp;

abstract class OrderDirection {
    const ASC = "asc";
    const DESC = "desc";

    public static function isOrderDirectionValid(?string $direction): bool {
        $reflector = new \ReflectionClass('Owncloud\OcisSdkPhp\OrderDirection');
        if (!in_array($direction, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}
