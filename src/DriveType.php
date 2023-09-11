<?php

namespace Owncloud\OcisSdkPhp;

class DriveType {
    const PROJECT = "project";
    const PERSONAL = "personal";
    const VIRTUAL = "virtual";

    public static function isTypeValid(?string $type): bool {
        $reflector = new \ReflectionClass('Owncloud\OcisSdkPhp\DriveType');
        if (!in_array($type, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}
