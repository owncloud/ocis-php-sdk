<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\EducationUserApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\EducationUser as EducationUserModel;
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

/**
 * Class representing a file or folder inside a Drive in ownCloud Infinite Scale
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class EducationUser extends BaseUser
{
    private ?string $primaryRole;
    private string $serviceUrl;
    private string $accessToken;
    private Client $guzzle;
    private Configuration $graphApiConfig;

    /**
     * @phpstan-var ConnectionConfig
     */
    private array $connectionConfig;

    /**
     * @param EducationUserModel $user
     * @param string $serviceUrl
     * @phpstan-param ConnectionConfig $connectionConfig
     * @param string $accessToken
     */
    public function __construct(
        EducationUserModel $user,
        string $serviceUrl,
        array $connectionConfig,
        string &$accessToken,
    ) {
        parent::__construct($user);
        $this->primaryRole = $user->getPrimaryRole();
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->connectionConfig = $connectionConfig;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');
        $this->guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
    }

    /**
     * Get the value of primaryRole
     */
    public function getPrimaryRole(): string|null
    {
        return $this->primaryRole;
    }

    /**
     * delete education user
     *
     * @throws BadRequestException
     * @throws ConflictException
     * @throws InvalidResponseException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     * @throws TooEarlyException
     * @throws UnauthorizedException
     */
    public function delete(): void
    {
        $apiInstance = new EducationUserApi(
            $this->guzzle,
            $this->graphApiConfig,
        );

        try {
            $apiInstance->deleteEducationUser($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
