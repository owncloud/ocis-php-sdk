<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\Permission as ApiPermission;
use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\NotImplementedException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

/**
 * A permission to a resource
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


    /**
     * @throws InvalidResponseException
     */
    public function __construct(
        ApiPermission $apiPermission,
        OcisResource  $resource,
        array         $connectionConfig,
        string        $serviceUrl,
        string        &$accessToken
    )
    {
        $this->apiPermission = $apiPermission;
        if (!is_string($apiPermission->getId())) {
            throw new InvalidResponseException(
                "Invalid id returned for permission '" . print_r($apiPermission->getId(), true) . "'"
            );
        }

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
        return $this->apiPermission->getId();
    }

    public function getType(): SharingLinkType
    {
        return $this->apiPermission->getLink()->getType();
    }

    public function getWebUrl(): string
    {
        return $this->apiPermission->getLink()->getWebUrl();
    }

    public function getDisplayName(): ?string
    {
        return $this->apiPermission->getLink()->getAtLibreGraphDisplayName();
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
        $link = $this->apiPermission->getLink();
        $link->setAtLibreGraphDisplayName($displayName);
        $this->apiPermission->setLink($link);

        try {
            $this->getDrivesPermissionsApi()->updatePermission(
                $this->resource->getId(),
                $this->resource->getId(),
                $this->getPermissionId(),
                $this->apiPermission
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
    public function setType(SharingLinkType $linkType): bool
    {
        $link = $this->apiPermission->getLink();
        $link->setType($linkType::EDIT);
        $this->apiPermission->setLink($link);

        try {
            $this->getDrivesPermissionsApi()->updatePermission(
                $this->resource->getId(),
                $this->resource->getId(),
                $this->getPermissionId(),
                $this->apiPermission
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        return true;
    }
}
