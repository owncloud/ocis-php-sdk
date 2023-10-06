<?php

namespace Owncloud\OcisPhpSdk;

enum DriveOrder: string
{
    case LASTMODIFIED = "lastModifiedDateTime";
    case NAME = "name";
}
