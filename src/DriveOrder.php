<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Possible order values for sorting drives
 */
enum DriveOrder: string
{
    case LASTMODIFIED = "lastModifiedDateTime";
    case NAME = "name";
}
