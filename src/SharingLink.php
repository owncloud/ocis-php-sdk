<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
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
class SharingLink
{
    private string $accessToken;
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
    private string $serviceUrl;
    private Configuration $graphApiConfig;
    private OcisResource $resource;
    private ApiPermission $apiPermission;
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
     */
    public function __construct(
        ApiPermission $apiPermission,
        OcisResource  $resource,
        array         $connectionConfig,
        string        $serviceUrl,
        string        &$accessToken
    ) {
        $this->apiPermission = $apiPermission;
        if (!is_string($apiPermission->getId())) {
            throw new InvalidResponseException(
                "Invalid id returned for permission '" . print_r($apiPermission->getId(), true) . "'"
            );
        }

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
        $this->resource = $resource;
        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');

        $this->connectionConfig = $connectionConfig;
    }

    private function getDrivesPermissionsApi(): DrivesPermissionsApi
    {
        $guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken)
        );
        if (array_key_exists('drivesPermissionsApi', $this->connectionConfig)) {
            return $this->connectionConfig['drivesPermissionsApi'];
        } else {
            return new DrivesPermissionsApi(
                $guzzle,
                $this->graphApiConfig
            );
        }
    }

    public function getPermissionId(): string
    {
        // in the constructor the value is checked for being the right type, but phan does not know
        // so simply cast to string
        return (string)$this->apiPermission->getId();
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

    public function getExpiry(): ?\DateTime
    {
        return $this->apiPermission->getExpirationDateTime();
    }

    /**
     * permanently delete the current sharing link
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     */
    public function delete(): bool
    {
        try {
            $this->getDrivesPermissionsApi()->deletePermission(
                $this->resource->getId(),
                $this->resource->getId(),
                $this->getPermissionId()
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        return true;
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
        $this->apiPermission->setLink($this->sharingLink);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->resource->getId(),
                $this->resource->getId(),
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

    /**
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
        $this->apiPermission->setLink($this->sharingLink);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->resource->getId(),
                $this->resource->getId(),
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

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws HttpException
     * @throws NotFoundException
     */
    public function setExpiration(\DateTime $expiration): bool
    {
        $this->apiPermission->setExpirationDateTime($expiration);

        try {
            $apiPermission = $this->getDrivesPermissionsApi()->updatePermission(
                $this->resource->getId(),
                $this->resource->getId(),
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
                $this->resource->getId(),
                $this->resource->getId(),
                $this->getPermissionId(),
                $newPassword
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($apiPermission instanceof OdataError) {
            throw new InvalidResponseException(
                "setPermissionPassword returned an OdataError - " . $apiPermission->getError()
            );
        }
        $this->apiPermission = $apiPermission;
        return true;
    }
}
