<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\Api\DrivesRootApi;
use OpenAPI\Client\Api\GroupApi;
use OpenAPI\Client\Api\MeDriveApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\Api\UserApi;
use OpenAPI\Client\Api\UsersApi;
use OpenAPI\Client\Api\EducationUserApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\EducationUser as EducationUserModel;
use OpenAPI\Client\Model\ObjectIdentity;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Quota;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;
use stdClass;
use OpenAPI\Client\Api\GroupsApi;
use OpenAPI\Client\Model\Group as OpenAPIGroup;

/**
 * Basic class to establish the connection to an ownCloud Infinite Scale instance
 *
 * @phpstan-type ConnectionConfig array{
 *                     'headers'?:array<string, mixed>,
 *                     'proxy'?:array{'http'?:string, 'https'?:string, 'no'?:array<string>}|string,
 *                     'verify'?:bool,
 *                     'webfinger'?:bool,
 *                     'guzzle'?:Client,
 *                     'drivesApi'?:DrivesApi,
 *                     'drivesGetDrivesApi'?:DrivesGetDrivesApi,
 *                     'drivesPermissionsApi'?:DrivesPermissionsApi,
 *                     'drivesRootApi'?:DrivesRootApi
 *  }
 */
class Ocis
{
    private const DECODE_TOKEN_ERROR_MESSAGE = 'Could not decode token.';
    /**
     * @ignore this const is only for internal use and should not show up in the documentation
     *         it is made public to allow other classes to show the same error message
     */
    public const FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE =
        'This function is not implemented yet! Place, name and signature of the function might change!';
    /**
     * @ignore this const is only for internal use and should not show up in the documentation
     *         it is made public to allow other classes to show the same error message
     */
    public const ENDPOINT_NOT_IMPLEMENTED_ERROR_MESSAGE =
        'This method is not implemented in this ocis version';
    private string $serviceUrl;
    private ?string $accessToken;
    private ?string $educationAccessToken;
    private Configuration $graphApiConfig;
    private Client $guzzle;
    private string $notificationsEndpoint = '/ocs/v2.php/apps/notifications/api/v1/notifications?format=json';

    /**
     * @phpstan-var ConnectionConfig
     */
    private array $connectionConfig;
    private string $ocisVersion = '';

    /**
     * @phpstan-param ConnectionConfig $connectionConfig
     *        valid config keys are: headers, proxy, verify, webfinger, guzzle
     *        headers has to be an array in the form like
     *        [
     *            'User-Agent' => 'testing/1.0',
     *            'Accept'     => 'application/json',
     *            'X-Foo'      => ['Bar', 'Baz']
     *        ]
     *        proxy is an array or a string that defines the proxy configuration, the schema is the same as for guzzle 7
     *              https://docs.guzzlephp.org/en/stable/request-options.html#proxy
     *        verify is a boolean to disable SSL checking
     *        webfinger is a boolean to enable webfinger discovery, in that case $serviceUrl is the webfinger url
     *        guzzle is a guzzle client instance that can be injected e.g. to be used for unit tests
     * @throws \InvalidArgumentException
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     */
    public function __construct(
        string $serviceUrl,
        ?string $accessToken = null,
        array $connectionConfig = [],
        ?string $educationAccessToken = null,
    ) {
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('Connection configuration is not valid');
        }
        $this->accessToken = $accessToken;
        $this->educationAccessToken = $educationAccessToken;
        if (array_key_exists('guzzle', $connectionConfig)) {
            $this->guzzle = $connectionConfig['guzzle'];
        } else {
            $token = $accessToken ?? $educationAccessToken;
            if ($token !== null) {
                $this->guzzle = new Client(self::createGuzzleConfig($connectionConfig, $token));
            } else {
                throw new \InvalidArgumentException('Invalid guzzle client.');
            }
        }
        if (array_key_exists('webfinger', $connectionConfig) && $connectionConfig['webfinger'] === true) {
            $this->serviceUrl = $this->getServiceUrlFromWebfinger($serviceUrl);
        } else {
            $this->serviceUrl = rtrim($serviceUrl, '/');
        }

        $this->connectionConfig = $connectionConfig;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost(
            $this->serviceUrl . '/graph',
        );
    }

    public function getServiceUrl(): string
    {
        return $this->serviceUrl;
    }

    /**
     * Helper function to check if the variable is a guzzle client
     * we need this because we want to call the check with call_user_func
     *
     * @phpstan-ignore-next-line phpstan does not understand that this method was called via call_user_func
     */
    private static function isGuzzleClient(mixed $guzzle): bool
    {
        return $guzzle instanceof Client;
    }

    /**
     * Helper function to check if the variable is a DrivesPermissionsApi
     * we need this because we want to call the check with call_user_func
     *
     * @phpstan-ignore-next-line phpstan does not understand that this method was called via call_user_func
     */
    private static function isDrivesPermissionsApi(mixed $api): bool
    {
        return $api instanceof DrivesPermissionsApi;
    }

    /**
     * Helper function to check if the variable is a DrivesApi
     * we need this because we want to call the check with call_user_func
     *
     * @phpstan-ignore-next-line phpstan does not understand that this method was called via call_user_func
     */
    private static function isDrivesApi(mixed $api): bool
    {
        return $api instanceof DrivesApi;
    }

    public static function isDrivesGetDrivesApi(mixed $api): bool
    {
        return $api instanceof DrivesGetDrivesApi;
    }

    public static function isDrivesRootApi(mixed $api): bool
    {
        return $api instanceof DrivesRootApi;
    }

    /**
     * @param array<mixed> $connectionConfig
     * @ignore This function is used for internal purposes only and should not be shown in the documentation.
     *         The function is public to make it testable and because its also used from other classes.
     */
    public static function isConnectionConfigValid(array $connectionConfig): bool
    {
        $validConnectionConfigKeys = [
            'headers' => 'is_array',
            'verify' => 'is_bool',
            'webfinger' => 'is_bool',
            'guzzle' => self::class . '::isGuzzleClient',
            'drivesPermissionsApi' => self::class . '::isDrivesPermissionsApi',
            'drivesApi' => self::class . '::isDrivesApi',
            'drivesGetDrivesApi' => self::class . '::isDrivesGetDrivesApi',
            'drivesRootApi' => self::class . '::isDrivesRootApi',
            'proxy' => 'is_array',
        ];
        foreach ($connectionConfig as $key => $check) {
            if (!array_key_exists($key, $validConnectionConfigKeys)) {
                return false;
            }

            if (!\call_user_func($validConnectionConfigKeys[$key], $check)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Combines passed-in config settings for guzzle with the default settings needed
     * for the class and returns the complete array
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     * @ignore This function is used for internal purposes only and should not be shown in the documentation.
     *         The function is public to make it testable.
     * @phpstan-param ConnectionConfig $connectionConfig
     */
    public static function createGuzzleConfig(array $connectionConfig, string $accessToken): array
    {
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('Connection configuration is not valid');
        }
        if (!isset($connectionConfig['headers'])) {
            $connectionConfig['headers'] = [];
        }
        $connectionConfig['headers'] = array_merge(
            $connectionConfig['headers'],
            ['Authorization' => 'Bearer ' . $accessToken],
        );
        return $connectionConfig;
    }

    /**
     * check for access token.
     *
     * @throws \InvalidArgumentException
     */
    private function checkIfAccessTokenExists(): void
    {
        if ($this->accessToken === null) {
            throw new \InvalidArgumentException(
                "This function cannot be used because no access token was provided.",
            );
        }
    }

    /**
     * check for access token of education user.
     *
     * @throws \InvalidArgumentException
     */
    private function checkIfEducationAccessTokenExists(): void
    {
        if ($this->educationAccessToken === null) {
            throw new \InvalidArgumentException(
                "This function cannot be used because no authentication token was provided for the educationUser endpoints.",
            );
        }
    }

    /**
     * Update the access token. Call this function after refreshing the access token.
     *
     * @throws \InvalidArgumentException
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->guzzle = new Client(Ocis::createGuzzleConfig($this->connectionConfig, $accessToken));
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws \InvalidArgumentException
     * @throws InternalServerErrorException
     */
    private function getServiceUrlFromWebfinger(string $webfingerUrl): string
    {
        $this->checkIfAccessTokenExists();
        // @phpstan-ignore-next-line access token if empty is caught by previous step.
        $tokenDataArray = explode(".", $this->accessToken);
        if (!array_key_exists(1, $tokenDataArray)) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " No payload found.",
            );
        }
        $plainPayload = base64_decode(\strtr($tokenDataArray[1], '-_', '+/'), true);
        if (!$plainPayload) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " Payload not Base64Url encoded.",
            );
        }
        $tokenPayload = json_decode($plainPayload, true);
        if (!is_array($tokenPayload)) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " Payload not valid JSON.",
            );
        }
        if (!array_key_exists('iss', $tokenPayload)) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " Payload does not contain 'iss' key.",
            );
        }
        if (!is_string($tokenPayload['iss'])) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " 'iss' key is not a string.",
            );
        }
        $iss = parse_url($tokenPayload['iss']);
        if (!is_array($iss) || !array_key_exists('host', $iss)) {
            throw new \InvalidArgumentException(
                self::DECODE_TOKEN_ERROR_MESSAGE .
                " Content of 'iss' has no 'host' part.",
            );
        }
        try {
            $webfingerResponse = $this->guzzle->get($webfingerUrl . '?resource=acct:me@' . $iss['host']);
        } catch (GuzzleException|GuzzleClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        $webfingerDecodedResponse = json_decode($webfingerResponse->getBody()->getContents(), true);
        if (
            !is_array($webfingerDecodedResponse) ||
            !array_key_exists('links', $webfingerDecodedResponse) ||
            !is_array($webfingerDecodedResponse['links'])
        ) {
            throw new InvalidResponseException('invalid webfinger response');
        }
        foreach ($webfingerDecodedResponse['links'] as $link) {
            if (
                is_array($link) &&
                array_key_exists('rel', $link) &&
                $link['rel'] === 'http://webfinger.owncloud/rel/server-instance' &&
                array_key_exists('href', $link)
            ) {
                return $link['href'];
            }
        }
        throw new InvalidResponseException('invalid webfinger response');
    }

    /**
     * returns the current oCIS version in semantic versioning format ( e.g. "5.0.5" )
     *
     * @return string
     * @throws InvalidResponseException
     */
    public function getOcisVersion(): string
    {
        if (($this->ocisVersion)) {
            return $this->ocisVersion;
        } else {
            $response = $this->guzzle->get($this->serviceUrl . '/ocs/v1.php/cloud/capabilities');
            $responseContent = $response->getBody()->getContents();

            $body = simplexml_load_string($responseContent);
            if (!isset($body->data->version->productversion)) {
                throw new InvalidResponseException('Missing product version element in XML response');
            }
            $version = (string)$body->data->version->productversion;
            $pattern = '(\d\.\d\.\d)';
            if (preg_match($pattern, $version, $matches)) {
                return $this->ocisVersion = $matches[0];
            } else {
                throw new InvalidResponseException('Ocis version format is invalid');
            }
        }
    }

    /**
     * Get all available drives
     *
     * @return array<Drive>
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getAllDrives(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC,
        ?DriveType      $type = null,
    ): array {
        if (array_key_exists('drivesGetDrivesApi', $this->connectionConfig)) {
            $apiInstance = $this->connectionConfig['drivesGetDrivesApi'];
        } else {
            $this->checkIfAccessTokenExists();
            $apiInstance = new DrivesGetDrivesApi(
                $this->guzzle,
                $this->graphApiConfig,
            );
        }
        $order = $this->getListDrivesOrderString($orderBy, $orderDirection);
        $filter = $this->getListDrivesFilterString($type);
        $drives = [];
        /**
         * The filter parameter of listAllDrives can be passed null,
         * but the generated PHP doc for it in libre-graph-api-php does not have ?string.
         * Filter might be null here, which is OK.
         * Suppress the message from phan.
         */

        try {
            /** @phan-suppress-next-line PhanTypeMismatchArgumentNullable */
            $allDrivesList = $apiInstance->listAllDrives($order, $filter);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($allDrivesList instanceof OdataError) {
            throw ExceptionHelper::getExceptionFromOdataError($allDrivesList, "listAllDrives");
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive(
                $apiDrive,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken,
                $this->getOcisVersion(),
            );
            $drives[] = $drive;
        }

        return $drives;
    }

    /**
     * Get all drives that the current user is a regular member of
     *
     * @return array<Drive>
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getMyDrives(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC,
        ?DriveType      $type = null,
    ): array {
        $this->checkIfAccessTokenExists();
        $apiInstance = new MeDrivesApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        $drives = [];
        $order = $this->getListDrivesOrderString($orderBy, $orderDirection);
        $filter = $this->getListDrivesFilterString($type);
        /**
         * The filter parameter of listAllDrives can be passed null,
         * but the generated PHP doc for it in libre-graph-api-php does not have ?string.
         * Filter might be null here, which is OK.
         * Suppress the message from phan.
         */

        try {
            /** @phan-suppress-next-line PhanTypeMismatchArgumentNullable */
            $allDrivesList = $apiInstance->listMyDrives($order, $filter);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($allDrivesList instanceof OdataError) {
            throw ExceptionHelper::getExceptionFromOdataError($allDrivesList, "listMyDrives");
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive(
                $apiDrive,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken,
                $this->getOcisVersion(),
            );
            $drives[] = $drive;
        }
        return $drives;
    }

    private function getListDrivesOrderString(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC,
    ): string {
        return $orderBy->value . ' ' . $orderDirection->value;
    }

    private function getListDrivesFilterString(
        ?DriveType $type = null,
    ): ?string {
        if ($type !== null) {
            $filter = 'driveType eq \'' . $type->value . '\'';
        } else {
            $filter = null;
        }
        return $filter;
    }

    /**
     * Retrieve a drive by its unique id
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws InternalServerErrorException
     */
    public function getDriveById(string $driveId): Drive
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new DrivesApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiDrive = $apiInstance->getDrive($driveId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiDrive instanceof OdataError) {
            throw new InvalidResponseException(
                "getDrive returned an OdataError - " . $apiDrive->getError(),
            );
        }
        return new Drive(
            $apiDrive,
            $this->connectionConfig,
            $this->serviceUrl,
            $this->accessToken,
            $this->getOcisVersion(),
        );
    }

    /**
     * Create a new drive (if the user has the permission to do so)
     *
     * @param int $quota in bytes
     * @return Drive
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \InvalidArgumentException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function createDrive(
        string $name,
        int $quota = 0,
        ?string $description = null,
    ): Drive {
        if ($quota < 0) {
            throw new \InvalidArgumentException('Quota cannot be less than 0');
        }
        if (array_key_exists('drivesApi', $this->connectionConfig)) {
            $apiInstance = $this->connectionConfig['drivesApi'];
        } else {
            $this->checkIfAccessTokenExists();
            $apiInstance = new DrivesApi(
                $this->guzzle,
                $this->graphApiConfig,
            );
        }
        $apiDrive = new ApiDrive(
            [
                'description' => $description,
                'name' => $name,
                'quota' => new Quota(['total' => $quota]),
            ],
        );
        try {
            $newlyCreatedDrive = $apiInstance->createDrive($apiDrive);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($newlyCreatedDrive instanceof ApiDrive) {
            return new Drive(
                $newlyCreatedDrive,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken,
                $this->getOcisVersion(),
            );
        }
        throw new InvalidResponseException(
            "Drive could not be created. '" .
            $newlyCreatedDrive->getError()->getMessage() .
            "'",
        );
    }

    /**
     * Get list of groups (if the user has the permission to do so)
     *
     * @param string $search
     * @param OrderDirection $orderBy
     * @param boolean $expandMembers
     * @return array<int,Group>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \InvalidArgumentException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getGroups(
        string $search = "",
        OrderDirection $orderBy = OrderDirection::ASC,
        bool $expandMembers = false,
    ) {
        $this->checkIfAccessTokenExists();
        $apiInstance = new GroupsApi($this->guzzle, $this->graphApiConfig);
        $orderByString = $orderBy->value === OrderDirection::ASC->value ? "displayName" : "displayName desc";
        try {
            $allGroupsList = $apiInstance->listGroups(
                $search,
                [$orderByString],
                [],
                $expandMembers ? ["members"] : null,
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($allGroupsList instanceof OdataError) {
            throw new InvalidResponseException(
                "listGroups returned an OdataError - " . $allGroupsList->getError(),
            );
        }
        $apiGroups = $allGroupsList->getValue() ?? [];
        $groupList = [];
        foreach ($apiGroups as $group) {
            $newGroup = new Group(
                $group,
                $this->serviceUrl,
                $this->connectionConfig,
                $this->accessToken,
            );
            $groupList[] = $newGroup;
        }
        return $groupList;
    }

    /**
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getResourceById(string $fileId): OcisResource
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->getServiceUrl() . '/dav/spaces/']);
        $this->checkIfAccessTokenExists();
        //@phpstan-ignore-next-line
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        try {
            $properties = [];
            foreach (ResourceMetadata::cases() as $property) {
                $properties[] = $property->value;
            }
            $responses = $webDavClient->propFindUnfiltered(rawurlencode($fileId), $properties);
            $resource = new OcisResource(
                $responses,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken,
            );
        } catch (SabreClientHttpException|SabreClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        // make sure there is again an element with index 0
        return $resource;
    }

    /**
     * retrieve users known by the system
     * NOTE: if this function is used by a normal user a search string with at least 3 characters should be provided
     *
     * @param string|null $search
     * @return array<User>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function getUsers(?string $search = null): array
    {
        $this->checkIfAccessTokenExists();
        $users = [];
        $apiInstance = new UsersApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $collectionOfApiUsers = $apiInstance->listUsers($search);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($collectionOfApiUsers instanceof OdataError) {
            throw new InvalidResponseException(
                "listUsers returned an OdataError - " . $collectionOfApiUsers->getError(),
            );
        }
        $apiUsers = $collectionOfApiUsers->getValue() ?? [];
        foreach ($apiUsers as $apiUser) {
            $users[] = new User($apiUser);
        }
        return $users;
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function getUserById(string $userId): User
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new UserApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiUser = $apiInstance->getUser($userId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiUser instanceof OdataError) {
            throw new InvalidResponseException(
                "getUser returned an OdataError - " . $apiUser->getError(),
            );
        }
        return new User($apiUser);
    }

    /**
     * Create a new education user (if the user has the permission to do so)
     *
     * @return EducationUser
     */
    public function createEducationUser(
        string $displayName,
        string $onPremisesSAMAccountName,
        string $issuer,
        string $issuerAssignedId,
        string $primaryRole,
        ?string $surname = null,
        ?string $givenName = null,
        ?string $mail = null,
        ?EducationUserApi $apiInstance = null,
    ): EducationUser {
        $this->checkIfEducationAccessTokenExists();
        if (!isset($apiInstance)) {
            $apiInstance = new EducationUserApi(
                $this->guzzle,
                $this->graphApiConfig,
            );
        }
        $identity = new ObjectIdentity(["issuer" => $issuer,"issuer_assigned_id" => $issuerAssignedId]);
        $educationUser = new EducationUserModel([
            "display_name" => $displayName,
            "surname" => $surname,
            "given_name" => $givenName,
            "mail" => $mail,
            "on_premises_sam_account_name" => $onPremisesSAMAccountName,
            "primary_role" => $primaryRole,
            "identities" => [$identity],
        ]);

        try {
            $apiUser = $apiInstance->createEducationUser($educationUser);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiUser instanceof OdataError) {
            throw new InvalidResponseException(
                "create user returned an OdataError - " . $apiUser->getError(),
            );
        }
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable
        return new EducationUser($apiUser, $this->serviceUrl, $this->connectionConfig, $this->educationAccessToken);
    }

    /**
     * retrieve education users known by the system
     *
     * @param array<string>|null $search
     * @return array<EducationUser>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function getEducationUsers(?array $search = null, ?EducationUserApi $apiInstance = null): array
    {
        $this->checkIfEducationAccessTokenExists();
        if (!isset($apiInstance)) {
            $apiInstance = new EducationUserApi(
                $this->guzzle,
                $this->graphApiConfig,
            );
        }
        $users = [];
        try {
            $collectionOfApiUsers = $apiInstance->listEducationUsers($search);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($collectionOfApiUsers instanceof OdataError) {
            throw new InvalidResponseException(
                "listUsers returned an OdataError - " . $collectionOfApiUsers->getError(),
            );
        }
        $apiUsers = $collectionOfApiUsers->getValue() ?? [];
        foreach ($apiUsers as $apiUser) {
            $users[] = new EducationUser($apiUser, $this->serviceUrl, $this->connectionConfig, $this->educationAccessToken);
        }
        return $users;
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function getEducationUserById(string $userId, ?EducationUserApi $apiInstance = null): EducationUser
    {
        $this->checkIfEducationAccessTokenExists();
        if (!isset($apiInstance)) {
            $apiInstance = new EducationUserApi(
                $this->guzzle,
                $this->graphApiConfig,
            );
        }
        try {
            $apiUser = $apiInstance->getEducationUser($userId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiUser instanceof OdataError) {
            throw new InvalidResponseException(
                "getUser returned an OdataError - " . $apiUser->getError(),
            );
        }
        return new EducationUser($apiUser, $this->serviceUrl, $this->connectionConfig, $this->educationAccessToken);
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     */
    public function getGroupById(string $groupId): Group
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new GroupApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $apiGroup = $apiInstance->getGroup($groupId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiGroup instanceof OdataError) {
            throw new InvalidResponseException(
                "getGroup returned an OdataError - " . $apiGroup->getError(),
            );
        }
        return new Group(
            $apiGroup,
            $this->serviceUrl,
            $this->connectionConfig,
            $this->accessToken,
        );
    }

    /**
     * @param array<mixed> $ocsResponse
     * @return bool
     */
    private function isNotificationResponseValid(array $ocsResponse): bool
    {
        return
            isset($ocsResponse['ocs']) &&
            is_array($ocsResponse['ocs']) &&
            array_key_exists('data', $ocsResponse['ocs']) &&
            (
                is_array($ocsResponse['ocs']['data']) ||
                is_null($ocsResponse['ocs']['data'])
            );
    }

    /**
     * Retrieve all unread notifications of the current user
     *
     * @return array<Notification>
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws \InvalidArgumentException
     * @throws InvalidResponseException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function getNotifications(): array
    {
        $this->checkIfAccessTokenExists();
        try {
            $response = $this->guzzle->get(
                $this->serviceUrl . $this->notificationsEndpoint,
            );
        } catch (GuzzleException|GuzzleClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        $content = $response->getBody()->getContents();
        /**
         * @phpstan-var array{
         *  'ocs':array{
         *      'data': ?array{
         *          int, array{
         *              notification_id: ?mixed,
         *              app?: ?string,
         *              user?: ?string,
         *              datetime?: ?string,
         *              object_id?: ?string,
         *              object_type?: ?string,
         *              subject?: ?string,
         *              subjectRich?: ?string,
         *              message?: ?string,
         *              messageRich?: ?string,
         *              messageRichParameters?:array{int, mixed}
         *          }
         *      }
         *  }
         *} $ocsResponse
         */
        $ocsResponse = (array)json_decode($content, true);

        if (!$this->isNotificationResponseValid($ocsResponse)) {
            throw new InvalidResponseException(
                'Notification response is invalid. Content: "' .  $content . '"',
            );
        }

        if (is_null($ocsResponse['ocs']['data'])) {
            $ocsResponse['ocs']['data'] = [];
        }
        $notifications = [];
        foreach ($ocsResponse['ocs']['data'] as $ocsData) {
            if (
                !isset($ocsData["notification_id"]) ||
                !is_string($ocsData["notification_id"]) ||
                $ocsData["notification_id"] === "") {
                throw new InvalidResponseException(
                    'Id is invalid or missing in notification response. Content: "' . $content . '"',
                );
            }
            $id = $ocsData["notification_id"];
            /**
             * @phpstan-var object{
             *    app: string,
             *    user: string,
             *    datetime: string,
             *    object_id: string,
             *    object_type: string,
             *    subject: string,
             *    subjectRich: string,
             *    message: string,
             *    messageRich: string,
             *    messageRichParameters: array{int, mixed}
             *  } $notificationContent
             */
            $notificationContent = new stdClass();
            foreach (
                [
                    "app",
                    "user",
                    "datetime",
                    "object_id",
                    "object_type",
                    "subject",
                    "subjectRich",
                    "message",
                    "messageRich",
                ] as $key
            ) {
                if (!isset($ocsData[$key])) {
                    $notificationContent->$key = "";
                } else {
                    $notificationContent->$key = $ocsData[$key];
                }
            }
            $notificationContent->{'messageRichParameters'} =
                (isset($ocsData["messageRichParameters"])) ? $ocsData["messageRichParameters"] : [];

            $notifications[] = new Notification(
                $this->accessToken,
                $this->connectionConfig,
                $this->serviceUrl,
                $id,
                $notificationContent,
            );
        }
        return $notifications;
    }

    /**
    * Create a new group (if the user has the permission to do so)
    *
    * @param string $groupName
    * @param string $description
    * @return Group
    * @throws BadRequestException
    * @throws ForbiddenException
    * @throws NotFoundException
    * @throws UnauthorizedException
    * @throws \InvalidArgumentException
    * @throws InvalidResponseException
    * @throws HttpException
    * @throws InternalServerErrorException
    */
    public function createGroup(string $groupName, string $description = ""): Group
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new GroupsApi($this->guzzle, $this->graphApiConfig);
        $group = new OpenAPIGroup(["display_name" => $groupName, "description" => $description]);
        try {
            $newlyCreatedGroup = $apiInstance->createGroup($group);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($newlyCreatedGroup instanceof OdataError) {
            throw new InvalidResponseException(
                "createGroup returned an OdataError - " . $newlyCreatedGroup->getError(),
            );
        }
        return new Group(
            $newlyCreatedGroup,
            $this->serviceUrl,
            $this->connectionConfig,
            $this->accessToken,
        );
    }

    /**
     * delete an existing group (if the user has the permission to do so)
     *
     * @param string $groupId
     * @return void
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \InvalidArgumentException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function deleteGroupByID(string $groupId): void
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new GroupApi($this->guzzle, $this->graphApiConfig);
        try {
            $apiInstance->deleteGroup($groupId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * @return array<ShareReceived>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function getSharedWithMe(): array
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new MeDriveApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $shareList = $apiInstance->listSharedWithMe();
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($shareList instanceof OdataError) {
            throw new InvalidResponseException(
                "listSharedWithMe returned an OdataError - " . $shareList->getError(),
            );
        }
        $apiShares = $shareList->getValue() ?? [];
        $shares = [];
        foreach ($apiShares as $share) {
            $shares[] = new ShareReceived(
                $share,
            );
        }
        return $shares;
    }

    /**
     * @return array<ShareCreated|ShareLink>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws InternalServerErrorException
     */
    public function getSharedByMe(): array
    {
        $this->checkIfAccessTokenExists();
        $apiInstance = new MeDriveApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        try {
            $shareList = $apiInstance->listSharedByMe();
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($shareList instanceof OdataError) {
            throw new InvalidResponseException(
                "listSharedByMe returned an OdataError - " . $shareList->getError(),
            );
        }
        if ($shareList->getValue() === null) {
            throw new InvalidResponseException(
                "listSharedByMe returned 'null' where an array of data were expected",
            );
        }
        $shares = [];
        foreach ($shareList->getValue() as $share) {
            $resourceId = empty($share->getId()) ?
                throw new InvalidResponseException(
                    "Invalid resource id '" . print_r($share->getId(), true) . "'",
                )
                : (string)$share->getId();

            $driveId = empty($share->getParentReference()) || empty($share->getParentReference()->getDriveId()) ?
                throw new InvalidResponseException(
                    "Invalid driveId '" . print_r($share->getParentReference(), true) . "'",
                )
                : (string)$share->getParentReference()->getDriveId();

            if (!is_iterable($share->getPermissions())) {
                throw new InvalidResponseException("Invalid permissions provided");
            }
            foreach ($share->getPermissions() as $apiSharePermission) {
                if ($apiSharePermission->getLink() === null) {
                    $shares[] = new ShareCreated(
                        $apiSharePermission,
                        $resourceId,
                        $driveId,
                        $this->connectionConfig,
                        $this->serviceUrl,
                        $this->accessToken,
                    );
                } else {
                    $shares[] = new ShareLink(
                        $apiSharePermission,
                        $resourceId,
                        $driveId,
                        $this->connectionConfig,
                        $this->serviceUrl,
                        $this->accessToken,
                    );
                }
            }
        }
        return $shares;
    }

    /**
     * Search resource globally or within drive/folder
     *
     * @param string $pattern The search pattern where it can be of format:
     *   - `mediatype:<pattern>`: Search by media type (e.g., `mediatype:*png*`).
     *   - `name:*<pattern>`: Search by resource name (e.g., `name:*der2`).
     *   - `<pattern>`: General search pattern (e.g., `fold*`, `*der1`, `subfolder`, `*fo*`).
     * oCIS has huge tests coverage where supported pattern can be found https://github.com/owncloud/ocis
     *
     * @param int|null $limit
     * @param string|null $scopeId scopeId could be driveId or folderId
     *
     * @return array<OcisResource>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InternalServerErrorException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws SabreClientException
     * @throws SabreClientHttpException
     * @throws UnauthorizedException
     * @throws \DOMException
     */
    public function searchResource(string $pattern, ?int $limit = null, ?string $scopeId = null): array
    {
        $pattern .= !is_null($scopeId) ? " scope:" . $scopeId : '';

        $webDavClient = new WebDavClient(['baseUri' => $this->getServiceUrl()]);
        $this->checkIfAccessTokenExists();
        //@phpstan-ignore-next-line
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);

        $responses = $webDavClient->sendReportRequest($pattern, $limit, '/remote.php/dav/spaces');
        $resources = [];
        foreach ($responses as $response) {
            $resources[] = new OcisResource(
                $response,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken,
            );
        }

        return $resources;
    }
}
