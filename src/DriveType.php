<?php

namespace Owncloud\OcisPhpSdk;

class DriveType
{
    public const PROJECT = "project";
    public const PERSONAL = "personal";
    public const VIRTUAL = "virtual";

    public static function isTypeValid(?string $type): bool
    {
        $reflector = new \ReflectionClass('Owncloud\OcisPhpSdk\DriveType');
        if (!in_array($type, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}
