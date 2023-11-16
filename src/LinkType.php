<?php

namespace Owncloud\OcisPhpSdk;

/**
 * Types of (public) links
 * see https://owncloud.dev/libre-graph-api/#/drives.permissions/CreateLink
 */
enum LinkType: string
{
    case VIEW = "view";
    case UPLOAD = "upload";
    case EDIT = "edit";
    case CREATE_ONLY = "createOnly";
    case BLOCKS_DOWNLOAD = "blocksDownload";
}
