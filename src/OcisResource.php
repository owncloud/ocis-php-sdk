<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\DriveItemInvite;
use OpenAPI\Client\Model\DriveRecipient;
use OpenAPI\Client\Model\OdataError;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
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
    private string $accessToken;
    private string $serviceUrl;
    /**
     * @phpstan-var array{
     *                      'headers'?:array<string, mixed>,
     *                      'verify'?:bool,
     *                      'webfinger'?:bool,
     *                      'guzzle'?:\GuzzleHttp\Client,
     *                      'drivesPermissionsApi'?:\OpenAPI\Client\Api\DrivesPermissionsApi,
     *                    }
     */
    private array $connectionConfig;
    private Configuration $graphApiConfig;

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
     * @phpstan-param array{
     *              'headers'?:array<string, mixed>,
     *              'verify'?:bool,
     *              'webfinger'?:bool,
     *              'guzzle'?:Client,
     *              'drivesPermissionsApi'?:DrivesPermissionsApi
     *             } $connectionConfig
     * @return void
     */
    public function __construct(
        array $metadata,
        array $connectionConfig,
        string $serviceUrl,
        string &$accessToken
    ) {
        $this->metadata = $metadata;
        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');

        $this->connectionConfig = $connectionConfig;
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
     * gets all possible permissions for the resource
     * @return array<SharingRole>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     */
    public function getRoles(): array
    {
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken)
        );

        if (array_key_exists('drivesPermissionsApi', $this->connectionConfig)) {
            $apiInstance = $this->connectionConfig['drivesPermissionsApi'];
        } else {
            $apiInstance = new DrivesPermissionsApi(
                $guzzle,
                $this->graphApiConfig
            );
        }
        try {
            $collectionOfPermissions = $apiInstance->listPermissions($this->getSpaceId(), $this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($collectionOfPermissions instanceof OdataError) {
            throw new InvalidResponseException(
                "listPermissions returned an OdataError - " . $collectionOfPermissions->getError()
            );
        }
        $apiRoles = $collectionOfPermissions->getAtLibreGraphPermissionsRolesAllowedValues() ?? [];
        $roles = [];
        foreach ($apiRoles as $role) {
            $roles[] = new SharingRole($role);
        }
        return $roles;
    }

    /**
     * @param array<int, User|Group> $recipients
     * @param SharingRole $role
     * @param \DateTime|null $expiration
     * @return bool
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function invite($recipients, SharingRole $role, ?\DateTime $expiration = null): bool
    {
        $requestObject = [];
        $requestObject['recipients'] = [];
        foreach ($recipients as $recipient) {
            $recipientRequestObject = [];
            $recipientRequestObject['object_id'] = $recipient->getId();
            if ($recipient instanceof Group) {
                $recipientRequestObject['@libre.graph.recipient.type'] = "group";
            }
            $requestObject['recipients'][] = new DriveRecipient($recipientRequestObject);
        }
        $requestObject['roles'] = [$role->getId()];
        if ($expiration !== null) {
            $expiration->setTimezone(new \DateTimeZone('Z'));
            $requestObject['expiration_date_time'] = $expiration->format('Y-m-d\TH:i:s:up');
        }

        if (array_key_exists('drivesPermissionsApi', $this->connectionConfig)) {
            $apiInstance = $this->connectionConfig['drivesPermissionsApi'];
        } else {
            $guzzle = new Client(
                Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken)
            );
            $apiInstance = new DrivesPermissionsApi(
                $guzzle,
                $this->graphApiConfig
            );
        }

        $inviteData = new DriveItemInvite($requestObject);
        try {
            $permission = $apiInstance->invite($this->getSpaceId(), $this->getId(), $inviteData);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permission instanceof OdataError) {
            throw new InvalidResponseException(
                "invite returned an OdataError - " . $permission->getError()
            );
        }
        return true;
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
