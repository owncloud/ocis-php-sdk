<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\Permission as ApiPermission;
use OpenAPI\Client\Model\OdataError;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
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
     * @throws InternalServerErrorException
     */
    public function setRole(SharingRole $role): bool
    {
        $apiPermission = new ApiPermission();
        $apiPermission->setRoles([$role->getId()]);
        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $apiPermission
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

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function getReceiver(): User|Group
    {
        $ocis = new Ocis($this->serviceUrl, $this->accessToken, $this->connectionConfig);
        $receiver = $this->apiPermission->getGrantedToV2();
        if ($receiver === null) {
            throw new InvalidResponseException(
                "could not determine the receiver, getGrantedToV2 returned 'null'"
            );
        }
        $user = $receiver->getUser();
        if ($user !== null && $user->getId() !== null) {
            // casting to string only to make phan happy
            return $ocis->getUserById((string)$user->getId());
        }
        $group = $receiver->getGroup();
        if ($group !== null && $group->getId() !== null) {
            // casting to string only to make phan happy
            return $ocis->getGroupById((string)$group->getId());
        }
        throw new InvalidResponseException(
            "could not determine the receiver, neither group nor user was returned - " .
            print_r($receiver, true)
        );
    }
}
