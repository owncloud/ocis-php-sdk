<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Possible order values for sorting a list of drives
 */
enum DriveOrder: string
{
    case LASTMODIFIED = "lastModifiedDateTime";
    case NAME = "name";
}
