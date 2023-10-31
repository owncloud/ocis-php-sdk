<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Possible order values for sorting a list of drives
 */
enum GroupOrder: string
{
    case DISPLAYNAME = "displayName";
    case DISPLAYNAMEDESCENDING = "displayName desc";

}
