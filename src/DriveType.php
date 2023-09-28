<?php

namespace Owncloud\OcisPhpSdk;

enum DriveType: string
{
    case PERSONAL = "personal";
    case PROJECT = "project";
    case VIRTUAL = "virtual";
}
