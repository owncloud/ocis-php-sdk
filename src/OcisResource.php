<?php

namespace Owncloud\OcisPhpSdk;

use Sabre\DAV\Xml\Property\ResourceType;
use Owncloud\OcisPhpSdk\ResourceMetadata;

class OcisResource
{
    /**
     * @var array<mixed>
     */
    private array $metadata;

    /**
     * @param array<mixed> $metadata of the resource
     *
     * @return void
     */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @param ResourceMetadata $property
     * @phpstan-ignore-next-line because this method returns diffrent array depending on the property
     * @return array|string
     */
    private function getMetadata(ResourceMetadata $property): array|string
    {
        $metadata = [];
        if (array_key_exists($property->value, $this->metadata)) {
            $metadata[$property->getKey()] = $this->metadata[$property->value];
        }
        if ($metadata === []) {
            throw new \Exception('could not find property "' . $property->getKey() . '" in response');
        }
        if ($metadata[$property->getKey()] === null && $property->getKey() !== "tags") {
            throw new \Exception('Invalid response from server');
        }
        if ($metadata[$property->getKey()] instanceof ResourceType) {
            return $metadata[$property->getKey()]->getValue();
        }
        /** @phpstan-ignore-next-line because next line might return mixed data*/
        return  $metadata[$property->getKey()] === null ? (string)$metadata[$property->getKey()] : $metadata[$property->getKey()];
    }

    /**
     * @return string
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
     */
    public function getType(): string
    {
        $resourceType = $this->getMetadata(ResourceMetadata::RESOURCETYPE);
        if (isset($resourceType[0]) && $resourceType[0] === "{DAV:}collection") {
            return "folder";
        } elseif ($resourceType === []) {
            return "file";
        }
        throw new \Exception("Received invalid data for the key \"resourcetype\" in the response array");
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        if ($this->getType() === "folder") {
            $size = $this->getMetadata(ResourceMetadata::FOLDERSIZE);
            if (is_numeric($size)) {
                return (int)$size;
            }
            throw new \Exception("Received an invalid value for size in the response");
        }
        $size = $this->getMetadata(ResourceMetadata::FILESIZE);
        if (is_numeric($size)) {
            return (int)$size;
        }
        throw new \Exception("Received an invalid value for size in the response");
    }

    /**
     * @return string
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
     */
    public function isFavorited(): bool
    {
        $result = $this->getMetadata(ResourceMetadata::FAVORITE);
        if (in_array($result, [1, 0, '1', '0'])) {
            return (bool)$result;
        }
        throw new \Exception("value of property \"favorite\" invalid in the server response");
    }

    /**
     * @return array<int,array<string,string>>
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
