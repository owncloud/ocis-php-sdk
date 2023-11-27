<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\OdataError;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

class ShareCreated extends Share
{
    public function getPermissionId(): string
    {
        // in the constructor the value is checked for being the right type, but phan does not know
        // so simply cast to string
        return (string)$this->apiPermission->getId();
    }

    /**
     * Change the Role of the particular Share.
     * Possible roles are defined by the resource and have to be queried using OcisResource::getRoles()
     * Roles for shares are not to be confused with the types of share links!
     * @see OcisResource::getRoles()
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     */
    public function setRole(SharingRole $role): bool
    {
        $this->apiPermission->setRoles([$role->getId()]);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $this->apiPermission
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($apiPermission instanceof OdataError) {
            throw new InvalidResponseException(
                "updatePermission returned an OdataError - " . $apiPermission->getError()
            );
        }
        $this->apiPermission = $apiPermission;
        return true;
    }
}
