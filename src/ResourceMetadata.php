<?php

namespace Owncloud\OcisPhpSdk;

/**
 * @ignore This is only used for internal purposes and should not show up in the documentation
 */
enum ResourceMetadata: string
{
    case ID = "{http://owncloud.org/ns}id";
    case SPACEID = "{http://owncloud.org/ns}spaceid";
    case FILEPARENT = "{http://owncloud.org/ns}file-parent";
    case NAME = "{http://owncloud.org/ns}name";
    case ETAG = "{DAV:}getetag";
    case PERMISSIONS = "{http://owncloud.org/ns}permissions";
    case RESOURCETYPE = "{DAV:}resourcetype";
    case FOLDERSIZE = "{http://owncloud.org/ns}size";
    case FILESIZE = "{DAV:}getcontentlength";
    case CONTENTTYPE = "{DAV:}getcontenttype";
    case LASTMODIFIED = "{DAV:}getlastmodified";
    case TAGS = "{http://owncloud.org/ns}tags";
    case FAVORITE = "{http://owncloud.org/ns}favorite";
    case CHECKSUMS = "{http://owncloud.org/ns}checksums";
    case PRIVATELINK = '{http://owncloud.org/ns}privatelink';



    public function getKey(): string
    {
        return match ($this) {
            self::ID => 'id',
            self::SPACEID => 'spaceid',
            self::FILEPARENT => 'file-parent',
            self::NAME => 'name',
            self::ETAG => 'etag',
            self::PERMISSIONS => 'permissions',
            self::RESOURCETYPE => 'resourcetype',
            self::FOLDERSIZE => 'foldersize',
            self::FILESIZE => 'filesize',
            self::CONTENTTYPE => 'contenttype',
            self::LASTMODIFIED => 'lastmodified',
            self::TAGS => 'tags',
            self::FAVORITE => 'favorite',
            self::CHECKSUMS => 'checksums',
            self::PRIVATELINK => 'privatelink',
        };
    }
}
