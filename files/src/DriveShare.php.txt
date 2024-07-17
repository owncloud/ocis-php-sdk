<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesRootApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Permission as ApiPermission;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ConflictException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\TooEarlyException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

class DriveShare extends Share
{
    protected function getDrivesRootApi(): DrivesRootApi
    {
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken)
        );
        if (array_key_exists('drivesRootApi', $this->connectionConfig)) {
            return $this->connectionConfig['drivesRootApi'];
        }

        return new DrivesRootApi(
            $guzzle,
            $this->graphApiConfig
        );
    }

    /**
     * Permanently delete the current drive share
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
            $this->getDrivesRootApi()->deletePermissionSpaceRoot(
                $this->driveId,
                $this->getPermissionId()
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        return true;
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
    private function updatePermission(ApiPermission $apiPermission): bool
    {
        try {
            $permission = $this->getDrivesRootApi()->updatePermissionSpaceRoot(
                $this->driveId,
                $this->getPermissionId(),
                $apiPermission
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($permission instanceof OdataError) {
            throw new InvalidResponseException(
                "updatePermission returned an OdataError - " . $permission->getError()
            );
        }
        $this->apiPermission = $permission;
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
    public function setRole(SharingRole $role): bool
    {
        $apiPermission = new ApiPermission();
        $apiPermission->setRoles([$role->getId()]);
        return $this->updatePermission($apiPermission);
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
    public function setExpiration(?\DateTimeImmutable $expiration): bool
    {
        if ($expiration !== null) {
            $expirationMutable = \DateTime::createFromImmutable($expiration);
        } else {
            $expirationMutable = null;
        }
        $apiPermission = new ApiPermission();
        $apiPermission->setExpirationDateTime($expirationMutable);
        return $this->updatePermission($apiPermission);
    }
}
