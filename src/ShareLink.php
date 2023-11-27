<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Permission as ApiPermission;
use OpenAPI\Client\Model\SharingLinkPassword;
use OpenAPI\Client\Model\SharingLinkType;
use OpenAPI\Client\Model\SharingLink as ApiSharingLink;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

/**
 * Class representing a public link to a resource
 */
class ShareLink extends Share
{
    private ApiSharingLink $sharingLink;

    /**
     * @throws InvalidResponseException
     * @phpstan-param array{
     *                       'headers'?:array<string, mixed>,
     *                       'verify'?:bool,
     *                       'webfinger'?:bool,
     *                       'guzzle'?:\GuzzleHttp\Client,
     *                       'drivesPermissionsApi'?:\OpenAPI\Client\Api\DrivesPermissionsApi,
     *                     } $connectionConfig
     * @ignore The developer using the SDK does not need to create ShareLink objects manually,
     *         but should use the OcisResource class to share resources using a link
     *         and that will create ShareLink objects
     */
    public function __construct(
        ApiPermission $apiPermission,
        string        $resourceId,
        string        $driveId,
        array         $connectionConfig,
        string        $serviceUrl,
        string        &$accessToken
    ) {
        parent::__construct(
            $apiPermission,
            $resourceId,
            $driveId,
            $connectionConfig,
            $serviceUrl,
            $accessToken
        );

        if (!($apiPermission->getLink() instanceof ApiSharingLink)) {
            throw new InvalidResponseException(
                "Invalid link returned for permission '" .
                print_r($apiPermission->getLink(), true) .
                "'"
            );
        }
        /**
         * The check above ensures that the type is correct, but phan does not know
         * @phan-suppress-next-next-line PhanPossiblyNullTypeMismatchProperty
         */
        $this->sharingLink = $apiPermission->getLink();

        if (!is_string($apiPermission->getLink()->getWebUrl())) {
            throw new InvalidResponseException(
                "Invalid webUrl returned for sharing link '" .
                print_r($apiPermission->getLink()->getWebUrl(), true) .
                "'"
            );
        }

        if (!($apiPermission->getLink()->getType() instanceof SharingLinkType)) {
            throw new InvalidResponseException(
                "Invalid type returned for sharing link '" .
                print_r($apiPermission->getLink()->getType(), true) .
                "'"
            );
        }
    }


    /**  phan-suppress-next-line PhanTypeMismatchReturnNullable */
    public function getType(): SharingLinkType
    {
        // in the constructor the value is checked for being the right type, but phan & phpstan does not know
        /** @phan-suppress-next-next-line PhanTypeMismatchReturnNullable */
        /** @phpstan-ignore-next-line */
        return $this->sharingLink->getType();
    }

    public function getWebUrl(): string
    {
        // in the constructor the value is checked for being the right type, but phan does not know
        // so simply cast to string
        return (string)$this->sharingLink->getWebUrl();
    }

    public function getDisplayName(): ?string
    {
        return $this->sharingLink->getAtLibreGraphDisplayName();
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     */
    public function setDisplayName(string $displayName): bool
    {
        $this->sharingLink->setAtLibreGraphDisplayName($displayName);
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
     */
    public function setType(SharingLinkType $linkType): bool
    {
        $this->sharingLink->setType($linkType);
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
     */
    public function setPassword(string $password): bool
    {
        $newPassword = new SharingLinkPassword([
            'password' => $password
        ]);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->setPermissionPassword(
                $this->driveId,
                $this->resourceId,
                $this->getPermissionId(),
                $newPassword
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($apiPermission instanceof OdataError) {
            throw new InvalidResponseException(
                "setPassword returned an OdataError - " . $apiPermission->getError()
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
     */
    public function updateLinkOfPermission(): bool
    {
        $this->apiPermission->setLink($this->sharingLink);

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
