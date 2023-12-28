<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesApi; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\DriveItemCreateLink;
use OpenAPI\Client\Model\DriveItemInvite;
use OpenAPI\Client\Model\DriveRecipient;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\TooEarlyException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Sabre\DAV\Xml\Property\ResourceType;
use Sabre\HTTP\ResponseInterface;

/**
 * Class representing a file or folder inside a Drive in ownCloud Infinite Scale
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class OcisResource
{
    /**
     * @var array<int, array<mixed>>
     */
    private array $metadata;
    private string $accessToken;
    private string $serviceUrl;

    /**
     * @phpstan-var ConnectionConfig
     */
    private array $connectionConfig;
    private Configuration $graphApiConfig;

    /**
     * @param array<int, array<mixed>> $metadata of the resource
     *        the format of the array is directly taken from the PROPFIND response
     *        returned by Sabre\DAV\Client
     *        for details about accepted metadata see: ResourceMetadata
     * @phpstan-param ConnectionConfig $connectionConfig
     * @param string $serviceUrl
     * @param string $accessToken
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     * @return void
     * @ignore The developer using the SDK does not need to create OcisResource objects manually,
     *         but should use the Drive class to query the server for resources
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
        // for metadata accept status codes of 200 and 425 (too early) status codes
        // any other status code is regarded as an error
        foreach ([200, 425] as $statusCode) {
            if (
                array_key_exists($statusCode, $this->metadata) &&
                array_key_exists($property->value, $this->metadata[$statusCode])
            ) {
                $metadata[$property->getKey()] = $this->metadata[$statusCode][$property->value];
                break;
            }
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
     * Gets all possible Roles for the resource
     * @return array<SharingRole>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
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
     * Invite a user or group to the resource.
     * Every recipient will result in an own ShareCreated object in the returned array.
     *
     * @param User|Group $recipient
     * @param SharingRole $role
     * @param \DateTimeImmutable|null $expiration
     * @return ShareCreated
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function invite($recipient, SharingRole $role, ?\DateTimeImmutable $expiration = null): ShareCreated
    {
        $driveItemInviteData = [];
        $driveItemInviteData['recipients'] = [];
        $recipientData = [];
        $recipientData['object_id'] = $recipient->getId();
        if ($recipient instanceof Group) {
            $recipientData['at_libre_graph_recipient_type'] = "group";
        }
        $driveItemInviteData['recipients'][] = new DriveRecipient($recipientData);
        $driveItemInviteData['roles'] = [$role->getId()];
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
            $driveItemInviteData['expiration_date_time'] = $expirationMutable;
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

        $inviteData = new DriveItemInvite($driveItemInviteData);
        try {
            $permissions = $apiInstance->invite($this->getSpaceId(), $this->getId(), $inviteData);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permissions instanceof OdataError) {
            throw new InvalidResponseException(
                "invite returned an OdataError - " . $permissions->getError()
            );
        }
        $permissionsValue = $permissions->getValue();
        if (
            $permissionsValue === null ||
            !array_key_exists(0, $permissionsValue) ||
            !($permissionsValue[0] instanceof Permission)
        ) {
            throw new InvalidResponseException(
                "invite returned invalid data " . print_r($permissionsValue, true)
            );
        }

        return new ShareCreated(
            $permissionsValue[0],
            $this->getId(),
            $this->getSpaceId(),
            $this->connectionConfig,
            $this->serviceUrl,
            $this->accessToken
        );
    }

    /**
     * create a new (public) link
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function createSharingLink(
        SharingLinkType $type = SharingLinkType::VIEW,
        ?\DateTimeImmutable $expiration = null,
        ?string $password = null,
        ?string $displayName = null
    ): ShareLink {
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
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
        } else {
            $expirationMutable = null;
        }

        $createLinkData = new DriveItemCreateLink([
            'type' => $type,
            'password' => $password,
            'expiration_date_time' => $expirationMutable,
            'display_name' => $displayName
        ]);
        try {
            $permission = $apiInstance->createLink($this->getSpaceId(), $this->getId(), $createLinkData);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permission instanceof OdataError) {
            throw new InvalidResponseException(
                "createLink returned an OdataError - " . $permission->getError()
            );
        }

        return new ShareLink(
            $permission,
            $this->getId(),
            $this->getSpaceId(),
            $this->connectionConfig,
            $this->serviceUrl,
            $this->accessToken
        );

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

    /**
     * Returns the private link to the resource.
     * This link can be used by any user with the correct permissions to navigate to the resource in the web UI.
     * The link is urldecoded.
     * @return string
     * @throws InvalidResponseException
     */
    public function getPrivatelink(): string
    {
        $privateLink = $this->getMetadata(ResourceMetadata::PRIVATELINK);
        if (!is_string($privateLink)) {
            throw new InvalidResponseException(
                'Invalid private link in response from server: ' . print_r($privateLink, true)
            );
        }
        return rawurldecode($privateLink);
    }

    /**
     * returns the content of this resource
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws TooEarlyException
     * @throws InternalServerErrorException
     */
    public function getContent(): string
    {
        $response = $this->getFileResponseInterface($this->getId());
        return $response->getBodyAsString();
    }

    /**
     * returns a stream to get the content of this resource
     *
     * @return resource
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws TooEarlyException
     * @throws InternalServerErrorException
     */
    public function getContentStream()
    {
        $response = $this->getFileResponseInterface($this->getId());
        return $response->getBodyAsStream();
    }

    /**
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws TooEarlyException
     * @throws InternalServerErrorException
     */
    private function getFileResponseInterface(string $fileId): ResponseInterface
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->serviceUrl . '/dav/spaces/']);
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        return $webDavClient->sendRequest("GET", $fileId);
    }
}
