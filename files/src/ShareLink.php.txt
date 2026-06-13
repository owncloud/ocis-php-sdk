<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\SharingLinkPassword;
use OpenAPI\Client\Model\SharingLinkType;
use OpenAPI\Client\Model\SharingLink as ApiSharingLink;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

/**
 * Class representing a public link to a resource
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class ShareLink extends Share
{
    public function getSharingLink(): ApiSharingLink
    {
        $sharingLink = $this->apiPermission->getLink();
        if (!$sharingLink instanceof ApiSharingLink) {
            throw new InvalidResponseException(
                "Invalid link returned for permission '" .
                print_r($sharingLink, true) .
                "'",
            );
        }
        return $sharingLink;
    }

    public function getType(): SharingLinkType
    {
        $type = $this->getSharingLink()->getType();
        if (!$type instanceof SharingLinkType) {
            throw new InvalidResponseException(
                "Invalid type returned for sharing link '" .
                print_r($type, true) .
                "'",
            );
        }
        return $type;
    }

    public function getWebUrl(): string
    {
        if (!is_string($this->getSharingLink()->getWebUrl())) {
            throw new InvalidResponseException(
                "Invalid webUrl returned for sharing link '" .
                print_r($this->getSharingLink()->getWebUrl(), true) .
                "'",
            );
        }
        return (string)$this->getSharingLink()->getWebUrl();
    }

    public function getDisplayName(): string
    {
        return (string)$this->getSharingLink()->getAtLibreGraphDisplayName();
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function setDisplayName(string $displayName): bool
    {
        $this->getSharingLink()->setAtLibreGraphDisplayName($displayName);
        return $this->updateLinkOfPermission();
    }

    /**
     * Change the type of the current ShareLink.
     * For details about the possible types see https://owncloud.dev/libre-graph-api/#/drives.permissions/CreateLink
     * Types of share links are not to be confused with roles for shares!
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function setType(SharingLinkType $linkType): bool
    {
        $this->getSharingLink()->setType($linkType);
        return $this->updateLinkOfPermission();
    }

    /**
     * @param string $password It may require a password policy. Set to empty sting to remove the password.
     * @return bool
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function setPassword(string $password): bool
    {
        $newPassword = new SharingLinkPassword([
            'password' => $password,
        ]);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->setPermissionPassword(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $newPassword,
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($apiPermission instanceof OdataError) {
            throw new InvalidResponseException(
                "setPassword returned an OdataError - " . $apiPermission->getError(),
            );
        }
        $this->apiPermission = $apiPermission;
        return true;
    }

    /**
     * @return true
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function updateLinkOfPermission(): bool
    {
        $this->apiPermission->setLink($this->getSharingLink());

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $this->apiPermission,
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
