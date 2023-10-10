<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Accepted drive types
 */
enum DriveType: string
{
    case PERSONAL = "personal";
    case PROJECT = "project";
    case VIRTUAL = "virtual";
}
