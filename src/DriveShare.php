<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\DrivesRootApi;
use OpenAPI\Client\ApiException;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\EndPointNotImplementedException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
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
     * Permanently delete the current share or share link
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     * @throws EndPointNotImplementedException
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

}
