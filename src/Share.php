<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Permission as ApiPermission;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

/**
 * Parent class representing different types of share objects
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class Share
{
    protected string $accessToken;
    /**
     * @phpstan-var ConnectionConfig
     */
    protected array $connectionConfig;
    protected string $serviceUrl;
    protected Configuration $graphApiConfig;
    protected ApiPermission $apiPermission;
    protected string $driveId;
    protected string $resourceId;


    /**
     * @throws InvalidResponseException
     * @phpstan-param ConnectionConfig $connectionConfig
     * @ignore The developer using the SDK does not need to create share objects manually,
     *         but should use the OcisResource class to invite people to a resource and
     *         that will create ShareCreated objects
     */
    public function __construct(
        ApiPermission $apiPermission,
        string        $resourceId,
        string        $driveId,
        array         $connectionConfig,
        string        $serviceUrl,
        string        &$accessToken,
    ) {
        $this->apiPermission = $apiPermission;
        $this->driveId = $driveId;

        $this->resourceId = $resourceId;
        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');

        $this->connectionConfig = $connectionConfig;
    }

    protected function getDrivesPermissionsApi(): DrivesPermissionsApi
    {

        if (array_key_exists('drivesPermissionsApi', $this->connectionConfig)) {
            return $this->connectionConfig['drivesPermissionsApi'];
        }
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
        return new DrivesPermissionsApi(
            $guzzle,
            $this->graphApiConfig,
        );
    }

    public function getPermissionId(): string
    {
        $id = $this->apiPermission->getId();
        if ($id === null || $id === '') {
            throw new InvalidResponseException(
                "Invalid id returned for permission '" . print_r($id, true) . "'",
            );
        }
        return $id;
    }

    public function getExpiration(): ?\DateTimeImmutable
    {
        $expiry = $this->apiPermission->getExpirationDateTime();
        if ($expiry === null) {
            return null;
        }

        return \DateTimeImmutable::createFromMutable($expiry);
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }
    /**
     * Permanently delete the current share or share link
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     */
    public function delete(): bool
    {
        try {
            $this->getDrivesPermissionsApi()->deletePermission(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        return true;
    }

    /**
     * Change the Expiration date for the current share or share link
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
    public function setExpiration(?\DateTimeImmutable $expiration): bool
    {
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
        } else {
            $expirationMutable = null;
        }
        $apiPermission = new ApiPermission();
        $apiPermission->setExpirationDateTime($expirationMutable);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $apiPermission,
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($apiPermission instanceof OdataError) {
            throw new InvalidResponseException(
                "updatePermission returned an OdataError - " . $apiPermission->getError(),
            );
        }
        $this->apiPermission = $apiPermission;
        return true;
    }
}
