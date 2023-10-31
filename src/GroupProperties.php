<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Possible order values for sorting a list of drives
 */
enum GroupProperties: string
{
    case ID = "id";
    case DESCRIPTION = "description";
    case DISPLAYNAME = "displayName";
    case MAIL = "mail";
    case MEMBERS = "members";

}
