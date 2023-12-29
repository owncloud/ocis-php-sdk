<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use OpenAPI\Client\Api\GroupApi;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Group as OpenApiGroup;
use OpenAPI\Client\Model\MemberReference;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use OpenAPI\Client\ApiException;

/**
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class Group
{
    private string|null $id;
    private string|null $description;
    private string|null $displayName;
    /**
     * @var ?array<int,string>
     */
    private array|null $groupTypes;
    /**
     * @var array<int,User>
     */
    private array $members;
    private Configuration $graphApiConfig;
    private Client $guzzle;
    private string $serviceUrl;
    private string $accessToken;

    /**
     * @phpstan-var ConnectionConfig
     * @ignore The developer using the SDK does not need to create Group objects manually,
     *         but should use the Ocis class to query the server for groups
     */
    private array $connectionConfig; /** @phpstan-ignore-line */

    /**
     * @param OpenApiGroup $openApiGroup
     * @param string $serviceUrl
     * @phpstan-param ConnectionConfig $connectionConfig
     * @param string $accessToken
     */
    public function __construct(
        OpenApiGroup $openApiGroup,
        string $serviceUrl,
        array $connectionConfig,
        string &$accessToken
    ) {
        $this->id = $openApiGroup->getId();
        $this->displayName = $openApiGroup->getDisplayName();
        $this->description = $openApiGroup->getDescription();
        $this->groupTypes = $openApiGroup->getGroupTypes();
        $openApiUser = $openApiGroup->getMembers() ?? [];
        $this->members = [];
        foreach ($openApiUser as $user) {
            $this->members[] = new User($user);
        }
        $this->accessToken = $accessToken;
        $this->serviceUrl = $serviceUrl;
        $this->connectionConfig = $connectionConfig;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');
        $this->guzzle = new Client(
            Ocis::createGuzzleConfig($connectionConfig, $this->accessToken)
        );
    }

    /**
     *
     * @return string
     */
    public function getId(): string
    {
        return (($this->id === null) || ($this->id === '')) ?
        throw new InvalidResponseException(
            "Invalid id returned for group '" . print_r($this->id, true) . "'"
        ) : (string)$this->id;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return (string)$this->description;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return (($this->displayName === null) || ($this->displayName === '')) ?
        throw new InvalidResponseException(
            "Invalid displayName returned for group '" . print_r($this->displayName, true) . "'"
        ) : $this->displayName;
    }

    /**
     * @return array<int,string>
     */
    public function getGroupTypes(): array
    {
        return $this->groupTypes ?? [];
    }

    /**
     * @return array<int,User>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * Set the value of members
     * @param User $member
     */
    public function setMembers(User $member): void
    {
        $this->members[] = $member;
    }

    /**
     * @param User $user
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     *
     * @return void
     */
    public function addUser($user): void
    {
        $apiInstance = new GroupApi($this->guzzle, $this->graphApiConfig);
        $memberRef = new MemberReference(
            [
                "at_odata_id" => $this->graphApiConfig->getHost(). "/v1.0/users/" . $user->getId()
            ]
        );
        try {
            $apiInstance->addMember($this->getId(), $memberRef);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        $this->setMembers($user);
    }

    /**
     * Remove an existing user from the current group
     *
     * @param User $user
     * @return void
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \InvalidArgumentException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function removeUser($user): void
    {
        $apiInstance = new GroupApi($this->guzzle, $this->graphApiConfig);
        try {
            $apiInstance->deleteMember($this->getId(), $user->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        foreach($this->members as $memberIndex => $member) {
            if ($member->getId() === $user->getId()) {
                unset($this->members[$memberIndex]);
            }
        }
        $this->members =  array_values($this->members);
    }

    /**
     * delete an existing group (if the user has the permission to do so)
     *
     * @return void
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \InvalidArgumentException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function delete(): void
    {
        $apiInstance = new GroupApi($this->guzzle, $this->graphApiConfig);
        try {
            $apiInstance->deleteGroup($this->getId());
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
