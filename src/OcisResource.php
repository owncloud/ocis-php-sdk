<?php

namespace Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Sabre\DAV\Xml\Property\ResourceType;

/**
 * Class representing a file or folder inside a Drive in ownCloud Infinite Scale
 */
class OcisResource
{
    /**
     * @var array<mixed>
     */
    private array $metadata;

    /**
     * @param array<mixed> $metadata of the resource
     *        the format of the array is directly taken from the PROPFIND response
     *        returned by Sabre\DAV\Client
     *        e.g:
     *        array (
     *          '{http://owncloud.org/ns}id' => <string>,
     *          '{http://owncloud.org/ns}fileid' => <string>,
     *          '{http://owncloud.org/ns}spaceid' => <string>,
     *          '{http://owncloud.org/ns}file-parent' => <string>,
     *          '{http://owncloud.org/ns}name' => <string>,
     *          '{DAV:}getetag' => <string>,
     *          '{http://owncloud.org/ns}permissions' => <string>,
     *          '{DAV:}resourcetype' => <ResourceType>,
     *          '{http://owncloud.org/ns}size' => <string>,
     *          '{DAV:}getlastmodified' => <string>,
     *          '{http://owncloud.org/ns}tags' => <null|string>,
     *          '{http://owncloud.org/ns}favorite' => <string>,
     *        )
     *
     * @return void
     */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;

    }

    /**
     * @param ResourceMetadata $property
     * @phpstan-ignore-next-line Because this method returns different array depending on the property
     * @return array|string
     * @throws InvalidResponseException
     */
    private function getMetadata(ResourceMetadata $property): array|string
    {
        $metadata = [];
        if (array_key_exists($property->value, $this->metadata)) {
            $metadata[$property->getKey()] = $this->metadata[$property->value];
        }
        if ($metadata === []) {
            throw new InvalidResponseException(
                'Could not find property "' . $property->getKey() . '" in response'
            );
        }
        if ($metadata[$property->getKey()] === null && $property->getKey() !== "tags") {
            throw new InvalidResponseException('Invalid response from server');
        }
        if ($metadata[$property->getKey()] instanceof ResourceType) {
            return $metadata[$property->getKey()]->getValue();
        }
        if ($metadata[$property->getKey()] === null) {
            return (string)$metadata[$property->getKey()];
        }
        /** @phpstan-ignore-next-line because next line might return mixed data*/
        return $metadata[$property->getKey()];
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getId(): string
    {
        $id = $this->getMetadata(ResourceMetadata::ID);
        if (is_array($id)) {
            return "";
        }
        return $id;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getSpaceId(): string
    {
        $spaceId = $this->getMetadata(ResourceMetadata::SPACEID);

        if (is_array($spaceId)) {
            return "";
        }
        return $spaceId;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getParent(): string
    {
        $parent = $this->getMetadata(ResourceMetadata::FILEPARENT);
        if (is_array($parent)) {
            return "";
        }
        return $parent;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getName(): string
    {
        $name = $this->getMetadata(ResourceMetadata::NAME);
        if (is_array($name)) {
            return "";
        }
        return $name;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getEtag(): string
    {
        $etag = $this->getMetadata(ResourceMetadata::ETAG);
        if (is_array($etag)) {
            return "";
        }
        return $etag;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getPermission(): string
    {
        $permission = $this->getMetadata(ResourceMetadata::PERMISSIONS);
        if (is_array($permission)) {
            return "";
        }
        return $permission;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getType(): string
    {
        $resourceType = $this->getMetadata(ResourceMetadata::RESOURCETYPE);
        if (isset($resourceType[0]) && $resourceType[0] === "{DAV:}collection") {
            return "folder";
        } elseif ($resourceType === []) {
            return "file";
        }
        throw new InvalidResponseException(
            "Received invalid data for the key \"resourcetype\" in the response array"
        );
    }

    /**
     * @return int
     * @throws InvalidResponseException
     */
    public function getSize(): int
    {
        if ($this->getType() === "folder") {
            $size = $this->getMetadata(ResourceMetadata::FOLDERSIZE);
            if (is_numeric($size)) {
                return (int)$size;
            }
            throw new InvalidResponseException("Received an invalid value for size in the response");
        }
        $size = $this->getMetadata(ResourceMetadata::FILESIZE);
        if (is_numeric($size)) {
            return (int)$size;
        }
        throw new InvalidResponseException("Received an invalid value for size in the response");
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getLastModifiedTime(): string
    {
        $modifiedTime = $this->getMetadata(ResourceMetadata::LASTMODIFIED);
        if (is_array($modifiedTime)) {
            return "";
        }
        return $modifiedTime;
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getContentType(): string
    {
        if ($this->getType() === "file") {
            $contentType = $this->getMetadata(ResourceMetadata::CONTENTTYPE);
            if (is_array($contentType)) {
                return implode($contentType);
            }
            return $contentType;
        }
        return "";
    }

    /**
     * @return array<int,string>
     * @throws InvalidResponseException
     */
    public function getTags(): array
    {
        $tags = $this->getMetadata(ResourceMetadata::TAGS);
        if ($tags === "") {
            return [];
        } elseif (is_string($tags)) {
            return explode(",", $tags);
        }
        return [];
    }

    /**
     * @return bool
     * @throws InvalidResponseException
     */
    public function isFavorited(): bool
    {
        $result = $this->getMetadata(ResourceMetadata::FAVORITE);
        if (in_array($result, [1, 0, '1', '0'])) {
            return (bool)$result;
        }
        throw new InvalidResponseException("Value of property \"favorite\" invalid in the server response");
    }

    /**
     * @return array<int,array<string,string>>
     * @throws InvalidResponseException
     */
    public function getCheckSums(): array
    {
        if ($this->getType() === "file" && $this->getSize() > 0) {
            $checkSum = $this->getMetadata(ResourceMetadata::CHECKSUMS);
            if (is_array($checkSum)) {
                return $checkSum;
            }
        }
        return [];
    }
}
