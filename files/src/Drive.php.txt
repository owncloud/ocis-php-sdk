<?php

namespace Owncloud\OcisPhpSdk;

use DateTime;
use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesRootApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\CollectionOfPermissionsWithAllowedValues;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\DriveUpdate;
use OpenAPI\Client\Model\DriveItem;
use OpenAPI\Client\Model\DriveItemInvite;
use OpenAPI\Client\Model\DriveRecipient;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\Quota;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ConflictException;
use Owncloud\OcisPhpSdk\Exception\EndPointNotImplementedException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\NotImplementedException;
use Owncloud\OcisPhpSdk\Exception\TooEarlyException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;

/**
 * Class representing a single drive/space in ownCloud Infinite Scale
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class Drive
{
    private ApiDrive $apiDrive;
    private string $accessToken;
    private string $webDavUrl = '';

    /**
     * @phpstan-var ConnectionConfig
     */
    private array $connectionConfig;
    private Configuration $graphApiConfig;
    private string $serviceUrl;
    private string $ocisVersion;

    // inviting users/groups to a drive is only possible starting from oCIS 6.0.0
    private const MIN_OCIS_VERSION_DRIVE_INVITE = "6.0.0";

    /**
     * @ignore The developer using the SDK does not need to create drives manually, but should use the Ocis class
     *         to get or create drives, so this constructor should not be listed in the documentation.
     * @phpstan-param ConnectionConfig $connectionConfig
     * @throws \InvalidArgumentException
     */
    public function __construct(
        ApiDrive $apiDrive,
        array $connectionConfig,
        string $serviceUrl,
        string &$accessToken,
        string $ocisVersion,
    ) {
        $this->apiDrive = $apiDrive;
        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        $this->ocisVersion = $ocisVersion;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');

        $this->connectionConfig = $connectionConfig;
    }

    private function createWebDavClient(): WebDavClient
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->getWebDavUrl()]);
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        return $webDavClient;
    }

    /**
     * @ignore This function is mainly for unit tests and should not be shown in the documentation
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getAlias(): string
    {
        return (string)$this->apiDrive->getDriveAlias();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getType(): DriveType
    {
        $driveTypeString = (string)$this->apiDrive->getDriveType();
        $driveType = DriveType::tryFrom($driveTypeString);
        if ($driveType instanceof DriveType) {
            return $driveType;
        }
        throw new InvalidResponseException(
            'Invalid DriveType returned by apiDrive: "' . print_r($driveTypeString, true) . '"',
        );
    }

    public function getId(): string
    {
        return (string)$this->apiDrive->getId();
    }

    public function getRoot(): ?DriveItem
    {
        return $this->apiDrive->getRoot();
    }

    public function getWebUrl(): string
    {
        return (string)$this->apiDrive->getWebUrl();
    }

    public function getWebDavUrl(): string
    {
        if (empty($this->webDavUrl)) {
            /**
             * phpstan complains "Offset 'web_dav_url' does not exist on OpenAPI\Client\Model\DriveItem"
             * but it does exist, see vendor/owncloud/libre-graph-api-php/lib/Model/DriveItem.php:83
             */
            /* @phpstan-ignore-next-line */
            $this->webDavUrl = rtrim((string)($this->getRoot())['web_dav_url'], '/') . '/';
        }
        return $this->webDavUrl;
    }

    /**
     * @throws InvalidResponseException
     */
    public function getLastModifiedDateTime(): DateTime
    {
        $date = $this->apiDrive->getLastModifiedDateTime();
        if ($date instanceof DateTime) {
            return $date;
        }
        throw new InvalidResponseException(
            'Invalid LastModifiedDateTime returned: "' . print_r($date, true) . '"',
        );
    }

    public function getName(): string
    {
        return $this->apiDrive->getName();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getQuota(): Quota
    {
        $quota = $this->apiDrive->getQuota();
        if ($quota instanceof Quota) {
            return $quota;
        }
        throw new InvalidResponseException(
            'Invalid quota returned: "' . print_r($quota, true) . '"',
        );
    }

    public function getRawData(): mixed
    {
        return $this->apiDrive->jsonSerialize();
    }

    /**
     * @return DrivesRootApi
     */
    private function getDrivesRootApi(): DrivesRootApi
    {
        if (array_key_exists('drivesRootApi', $this->connectionConfig)) {
            return $this->connectionConfig['drivesRootApi'];
        }

        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
        return new DrivesRootApi(
            $guzzle,
            $this->graphApiConfig,
        );
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws HttpException
     */
    public function isDisabled(): bool
    {
        $this->updateDriveObject();
        $root = $this->apiDrive->getRoot();
        if (!($root instanceof DriveItem)) {
            throw new InvalidResponseException(
                'Could not get root of drive "' . print_r($root, true) . '"',
            );
        }
        $deleted = $root->getDeleted();
        if ($deleted === null) {
            return false;
        }
        if ($deleted->getState() === 'trashed') {
            return true;
        }
        return false;
    }

    /**
     * Deletes the current drive irreversibly.
     * A drive can only be deleted if it has already been disabled.
     * Calling this function on a drive that is not disabled will have no effect.
     * Only project spaces can be deleted.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function delete(): void
    {
        $connectionConfig = array_merge(
            $this->connectionConfig,
            ['headers' => ['Purge' => 'T']],
        );
        $guzzle = new Client(
            Ocis::createGuzzleConfig($connectionConfig, $this->accessToken),
        );

        $apiInstance = new DrivesApi(
            $guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiInstance->deleteDrive($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * Disables the current drive without deleting it.
     * Disabling a drive is the prerequisite for deleting it.
     * Calling this function on a drive that is already disabled will have no effect.
     * Only project spaces can be disabled.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function disable(): void
    {
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
        $apiInstance = new DrivesApi(
            $guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiInstance->deleteDrive($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * Enables the current drive.
     * Calling this function on a drive that is already enabled will have no effect.
     * Only project spaces can be enabled.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function enable(): void
    {
        $connectionConfig = array_merge(
            $this->connectionConfig,
            ['headers' => ['Restore' => 'true']],
        );
        $guzzle = new Client(
            Ocis::createGuzzleConfig($connectionConfig, $this->accessToken),
        );

        $apiInstance = new DrivesApi(
            $guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiInstance->updateDrive($this->getId(), new DriveUpdate(['name' => $this->getName()]));
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function setName(string $name): Drive
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function setQuota(int $quota): Drive
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function setDescription(string $description): Drive
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     *
     * The type of $image is likely to be \GdImage when implemented.
     * That would require the php-gd extension to be installed.
     */
    public function setImage(object $image): Drive
    {
        // upload image to dav/spaces/<space-id>/.space/<image-name>
        // PATCH space
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function setReadme(string $readme): Drive
    {
        // upload content of $readme to dav/spaces/<space-id>/.space/readme.md
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * List the content of a specific path in the current drive
     * @param string $path
     *
     * @return array<OcisResource>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getResources(string $path = "/"): array
    {
        $resources = $this->makePropfindRequest($path);

        unset($resources[0]); // skip first propfind response, because its the parent folder
        // make sure there is again an element with index 0
        return array_values($resources);
    }

    /**
     * Download and return the content of the file at the given path in the current drive
     *
     * @return callable|resource|string
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getFile(string $path)
    {
        $webDavClient = $this->createWebDavClient();
        return $webDavClient->sendRequest("GET", rawurlencode(ltrim($path, "/")))->getBody();
    }

    /**
     * Return a stream resource for the file at the given path in the current drive.
     * The content of the file can be read from the stream.
     *
     * @param string $path
     *
     * @return resource
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function getFileStream(string $path)
    {
        $webDavClient = $this->createWebDavClient();
        return $webDavClient->sendRequest(
            "GET",
            $this->webDavUrl . rawurlencode(ltrim($path, "/")),
        )->getBodyAsStream();
    }


    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws HttpException
     */
    public function createFolder(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('MKCOL', rawurlencode(ltrim($path, "/")));
        return true;
    }

    /**
     * send propfind request
     * @param string $path
     *
     * @return array<OcisResource>
     */
    private function makePropfindRequest(string $path = "/"): array
    {
        $resources = [];
        $webDavClient = $this->createWebDavClient();
        try {
            $properties = [];
            foreach (ResourceMetadata::cases() as $property) {
                $properties[] = $property->value;
            }
            $responses = $webDavClient->propFindUnfiltered(rawurlencode(ltrim($path, "/")), $properties, 1);
            foreach ($responses as $response) {
                $resources[] = new OcisResource(
                    $response,
                    $this->connectionConfig,
                    $this->serviceUrl,
                    $this->accessToken,
                );
            }
            return $resources;
        } catch (SabreClientHttpException|SabreClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * get a specific resource in the current drive
     * @param string $path
     *
     * @return OcisResource
     */
    public function getResource(string $path = "/"): OcisResource
    {
        $resources = $this->makePropfindRequest($path);
        return $resources[0];
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function getResourceMetadataById(string $id): \stdClass
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * Create a new file, if it doesn't exist and update the content of the file if the file exists
     *
     * @param string $path
     * @param resource|string|null $resource
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    private function makePutRequest(string $path, $resource): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('PUT', rawurlencode(ltrim($path, "/")), $resource);
        return true;
    }

    /**
     * Upload the given content to a specific path in the current drive
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function uploadFile(string $path, string $content): bool
    {
        return $this->makePutRequest($path, $content);
    }

    /**
     * Stream the given resource to a specific path in the current drive
     *
     * @param resource|string|null $resource File resource pointing to the file to be uploaded
     *
     * @return bool
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function uploadFileStream(string $path, $resource): bool
    {
        if (is_resource($resource)) {
            return $this->makePutRequest($path, $resource);
        }
        throw new \InvalidArgumentException('Provided resource is not valid.');
    }

    /**
     * Delete (move to trash-bin) the Ocis resource (file or folder) at the given path in the current drive
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function deleteResource(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest("DELETE", rawurlencode(ltrim($path, "/")));
        return true;
    }

    /**
     * Move or rename an Ocis resource in the current drive
     *
     * @param string $sourcePath
     * @param string $destinationPath
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function moveResource(string $sourcePath, string $destinationPath): bool
    {
        $webDavClient = $this->createWebDavClient();
        $destinationUrl = $this->webDavUrl . rawurlencode(ltrim($destinationPath, "/"));
        $webDavClient->sendRequest('MOVE', "$sourcePath", null, ['Destination' => "$destinationUrl"]);
        return true;
    }

    /**
     * Empty the trash-bin of the current drive. ALL FILES AND FOLDERS IN THE TRASH-BIN WILL BE DELETED!
     * THIS ACTION CANNOT BE REVERTED!
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function emptyTrashbin(): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('DELETE', "/dav/spaces/trash-bin/" . $this->getId());
        return true;
    }

    private function updateDriveObject(): void
    {
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
        $apiInstance = new DrivesApi(
            $guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiDrive = $apiInstance->getDrive($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiDrive instanceof OdataError) {
            throw new InvalidResponseException(
                "getDrive returned an OdataError - " . $apiDrive->getError(),
            );
        }

        $this->apiDrive = $apiDrive;
    }

    /**
     * Invite a user or group to the drive.
     *
     * @param User|Group $recipient
     * @param SharingRole $role
     * @param \DateTimeImmutable|null $expiration
     * @return Permission
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     * @throws EndPointNotImplementedException
     */
    public function invite(User|Group $recipient, SharingRole $role, ?\DateTimeImmutable $expiration = null): Permission
    {
        if (version_compare($this->ocisVersion, self::MIN_OCIS_VERSION_DRIVE_INVITE, '<')) {
            throw new EndPointNotImplementedException(Ocis::ENDPOINT_NOT_IMPLEMENTED_ERROR_MESSAGE);
        }

        $driveInviteData = [];
        $driveInviteData['recipients'] = [];
        $recipientData = [];
        $recipientData['object_id'] = $recipient->getId();
        if ($recipient instanceof Group) {
            $recipientData['at_libre_graph_recipient_type'] = "group";
        }
        $driveInviteData['recipients'][] = new DriveRecipient($recipientData);
        $driveInviteData['roles'] = [$role->getId()];
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
            $driveInviteData['expiration_date_time'] = $expirationMutable;
        }

        $inviteData = new DriveItemInvite($driveInviteData);
        try {
            $permissions = $this->getDrivesRootApi()->inviteSpaceRoot($this->getId(), $inviteData);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permissions instanceof OdataError) {
            throw new InvalidResponseException(
                "invite returned an OdataError - " . $permissions->getError(),
            );
        }
        $permissionsValue = $permissions->getValue();
        if (
            $permissionsValue === null ||
            !array_key_exists(0, $permissionsValue) ||
            !($permissionsValue[0] instanceof Permission)
        ) {
            throw new InvalidResponseException(
                "invite returned invalid data " . print_r($permissionsValue, true),
            );
        }

        $this->updateDriveObject();
        return $permissionsValue[0];
    }

    /**
     * Gets all possible roles for the drive ( Project drive )
     * @return array<SharingRole>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     * @throws EndPointNotImplementedException
     */
    public function getRoles(): array
    {
        if (version_compare($this->ocisVersion, self::MIN_OCIS_VERSION_DRIVE_INVITE, '<')) {
            throw new EndPointNotImplementedException(Ocis::ENDPOINT_NOT_IMPLEMENTED_ERROR_MESSAGE);
        }
        $apiRoles = $this->sendGetPermissionsRequest()->getAtLibreGraphPermissionsRolesAllowedValues();
        if (empty($apiRoles)) {
            throw new InvalidResponseException(
                'Drive has no roles',
            );
        }
        $roles = [];
        foreach ($apiRoles as $role) {
            $roles[] = new SharingRole($role);
        }
        return $roles;
    }

    /**
     * Permanently delete the current drive share
     * $permissionId will be provided by getPermissionId()
     * @param string $permissionId
     * @return bool
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     */
    public function deletePermission(string $permissionId): bool
    {
        try {
            $this->getDrivesRootApi()->deletePermissionSpaceRoot(
                $this->getId(),
                $permissionId,
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        $this->updateDriveObject();
        return true;
    }

    /**
     * get permissions collection information
     * @return CollectionOfPermissionsWithAllowedValues
     * owner/co-owner receives all sharing permissions
     * non-owner receives only the sharing permissions that apply to the caller
     *
     * @throws BadRequestException
     * @throws ConflictException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws TooEarlyException
     * @throws UnauthorizedException
     */
    private function sendGetPermissionsRequest(): CollectionOfPermissionsWithAllowedValues
    {
        try {
            $collectionOfPermissions = $this->getDrivesRootApi()->listPermissionsSpaceRoot($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($collectionOfPermissions instanceof OdataError) {
            throw new InvalidResponseException(
                "listPermissions returned an OdataError - " . $collectionOfPermissions->getError(),
            );
        }
        return $collectionOfPermissions;
    }

    /**
     * get all permission assigned on drive
     * @return array<Permission>
     * @throws InvalidResponseException
     * @throws EndPointNotImplementedException
     */
    public function getPermissions(): array
    {
        if (version_compare($this->ocisVersion, self::MIN_OCIS_VERSION_DRIVE_INVITE, '<')) {
            throw new EndPointNotImplementedException(Ocis::ENDPOINT_NOT_IMPLEMENTED_ERROR_MESSAGE);
        }
        $permissions = $this->sendGetPermissionsRequest()->getValue();
        if (empty($permissions)) {
            throw new InvalidResponseException(
                'Expected Collection of Permission but got empty array.',
            );
        }
        return $permissions;
    }

    /**
     * @throws TooEarlyException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws ConflictException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     * @throws HttpException
     * @throws InvalidResponseException
     */
    private function updatePermission(string $permissionId, Permission $apiPermission): bool
    {
        try {
            $permission = $this->getDrivesRootApi()->updatePermissionSpaceRoot(
                $this->getId(),
                $permissionId,
                $apiPermission,
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permission instanceof OdataError) {
            throw new InvalidResponseException(
                "updatePermission returned an OdataError - " . $permission->getError(),
            );
        }
        $this->updateDriveObject();
        return true;
    }

    /**
     * Change the Role of the particular Drive Share.
     * Possible roles are defined by the drive and have to be queried using Drive::getRoles()
     * Roles for shares are not to be confused with the types of share links!
     * @see Drive::getRoles()
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function setPermissionRole(string $permissionId, SharingRole $role): bool
    {
        $apiPermission = new Permission();
        $apiPermission->setRoles([$role->getId()]);
        return $this->updatePermission($permissionId, $apiPermission);
    }

    /**
     * Change the Expiration date for the current drive share
     * Set to null to remove the expiration date
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function setPermissionExpiration(string $permissionId, ?\DateTimeImmutable $expiration): bool
    {
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
        } else {
            $expirationMutable = null;
        }
        $apiPermission = new Permission();
        $apiPermission->setExpirationDateTime($expirationMutable);
        return $this->updatePermission($permissionId, $apiPermission);
    }

    /**
     * @param array<string> $tags
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function tagResource(string $path, array $tags): void
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

    /**
     * @param array<string> $tags
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function untagResource(string $path, array $tags): void
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }
}
