<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\Api\GroupApi;
use OpenAPI\Client\Api\MeDriveApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\Api\UsersApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\Quota;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;
use stdClass;
use OpenAPI\Client\Api\GroupsApi;
use OpenAPI\Client\Model\Group as OpenAPIGrop;

/**
 * Basic class to establish the connection to an ownCloud Infinite Scale instance
 */
class Ocis
{
    private const DECODE_TOKEN_ERROR_MESSAGE = 'could not decode token';
    public const FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE =
        'This function is not implemented yet! Place, name and signature of the function might change!';
    private string $serviceUrl;
    private string $accessToken;
    private ?DrivesApi $drivesApiInstance = null;
    private ?DrivesGetDrivesApi $drivesGetDrivesApiInstance = null;
    private Configuration $graphApiConfig;
    private Client $guzzle;
    private string $notificationsEndpoint = '/ocs/v2.php/apps/notifications/api/v1/notifications?format=json';

    /**
     * @phpstan-var array{'headers'?:array<string, mixed>, 'verify'?:bool, 'webfinger'?:bool, 'guzzle'?:Client}
     */
    private array $connectionConfig;

    /**
     * @phpstan-param array{
     *                        'headers'?:array<string, mixed>,
     *                        'verify'?:bool,
     *                        'webfinger'?:bool,
     *                        'guzzle'?:Client
     *                        } $connectionConfig
     *        valid config keys are: headers, verify, webfinger, guzzle
     *        headers has to be an array in the form like
     *        [
     *            'User-Agent' => 'testing/1.0',
     *            'Accept'     => 'application/json',
     *            'X-Foo'      => ['Bar', 'Baz']
     *        ]
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
     */
    public function __construct(
        string $serviceUrl,
        string $accessToken,
        array $connectionConfig = []
    ) {
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('Connection configuration is not valid');
        }
        $this->accessToken = $accessToken;
        if (array_key_exists('guzzle', $connectionConfig)) {
            $this->guzzle = $connectionConfig['guzzle'];
        } else {
            $this->guzzle = new Client(self::createGuzzleConfig($connectionConfig, $this->accessToken));
        }
        if (array_key_exists('webfinger', $connectionConfig) && $connectionConfig['webfinger'] === true) {
            $this->serviceUrl = $this->getServiceUrlFromWebfinger($serviceUrl);
        } else {
            $this->serviceUrl = rtrim($serviceUrl, '/');
        }

        $this->connectionConfig = $connectionConfig;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost(
            $this->serviceUrl . '/graph'
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
            'guzzle' => 'self::isGuzzleClient',
            'drivesPermissionsApi' => 'self::isDrivesPermissionsApi',
            'drivesApi' => 'self::isDrivesApi'
        ];
        foreach ($connectionConfig as $key => $check) {
            if (!array_key_exists($key, $validConnectionConfigKeys)) {
                return false;
            }

            // phpstan does not understand that the `$validConnectionConfigKeys` array has values that are callable
            // @phpstan-ignore-next-line
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
     * @phpstan-param array{
     *                       'headers'?:array<string, mixed>,
     *                       'verify'?:bool,
     *                       'webfinger'?:bool,
     *                       'guzzle'?:Client
     *                       } $connectionConfig
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
            ['Authorization' => 'Bearer ' . $accessToken]
        );
        return $connectionConfig;
    }

    /**
     * @ignore This function is mainly for unit tests and should not be shown in the documentation
     */
    public function setDrivesApiInstance(DrivesApi|null $apiInstance): void
    {
        $this->drivesApiInstance = $apiInstance;
    }

    /**
     * @ignore This function is mainly for unit tests and should not be shown in the documentation
     */
    public function setDrivesGetDrivesApiInstance(DrivesGetDrivesApi|null $apiInstance): void
    {
        $this->drivesGetDrivesApiInstance = $apiInstance;
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
        $this->drivesApiInstance = null;
        $this->drivesGetDrivesApiInstance = null;
    }

    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws \InvalidArgumentException
     */
    private function getServiceUrlFromWebfinger(string $webfingerUrl): string
    {
        $tokenDataArray = explode(".", $this->accessToken);
        if (!array_key_exists(1, $tokenDataArray)) {
            throw new \InvalidArgumentException(self::DECODE_TOKEN_ERROR_MESSAGE);
        }
        $plainPayload = base64_decode($tokenDataArray[1], true);
        if (!$plainPayload) {
            throw new \InvalidArgumentException(self::DECODE_TOKEN_ERROR_MESSAGE);
        }
        $tokenPayload = json_decode($plainPayload, true);
        if (!is_array($tokenPayload) || !array_key_exists('iss', $tokenPayload)) {
            throw new \InvalidArgumentException(self::DECODE_TOKEN_ERROR_MESSAGE);
        }
        $iss = parse_url($tokenPayload['iss']);
        if (!is_array($iss) || !array_key_exists('host', $iss)) {
            throw new \InvalidArgumentException(self::DECODE_TOKEN_ERROR_MESSAGE);
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
     */
    public function getAllDrives(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC,
        DriveType      $type = null
    ): array {
        if ($this->drivesGetDrivesApiInstance === null) {
            $apiInstance = new DrivesGetDrivesApi(
                $this->guzzle,
                $this->graphApiConfig
            );
        } else {
            $apiInstance = $this->drivesGetDrivesApiInstance;
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
            // ToDo: understand how this can happen, and what to do about it.
            throw new InvalidResponseException(
                "listAllDrives returned an OdataError - " . $allDrivesList->getError()
            );
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive(
                $apiDrive,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken
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
     */
    public function getMyDrives(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC,
        DriveType      $type = null
    ): array {
        $apiInstance = new MeDrivesApi(
            $this->guzzle,
            $this->graphApiConfig
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
            // ToDo: understand how this can happen, and what to do about it.
            throw new InvalidResponseException(
                "listMyDrives returned an OdataError - " . $allDrivesList->getError()
            );
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive(
                $apiDrive,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken
            );
            $drives[] = $drive;
        }
        return $drives;
    }

    private function getListDrivesOrderString(
        DriveOrder     $orderBy = DriveOrder::NAME,
        OrderDirection $orderDirection = OrderDirection::ASC
    ): string {
        return $orderBy->value . ' ' . $orderDirection->value;
    }

    private function getListDrivesFilterString(
        DriveType $type = null
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
     */
    public function getDriveById(string $driveId): Drive
    {
        $apiInstance = new DrivesApi(
            $this->guzzle,
            $this->graphApiConfig
        );
        try {
            $apiDrive = $apiInstance->getDrive($driveId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($apiDrive instanceof OdataError) {
            throw new InvalidResponseException(
                "getDrive returned an OdataError - " . $apiDrive->getError()
            );
        }
        return new Drive(
            $apiDrive,
            $this->connectionConfig,
            $this->serviceUrl,
            $this->accessToken
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
     */
    public function createDrive(
        string $name,
        int $quota = 0,
        string $description = null
    ): Drive {
        if ($quota < 0) {
            throw new \InvalidArgumentException('Quota cannot be less than 0');
        }
        if ($this->drivesApiInstance === null) {
            $apiInstance = new DrivesApi(
                $this->guzzle,
                $this->graphApiConfig
            );
        } else {
            $apiInstance = $this->drivesApiInstance;
        }

        $apiDrive = new ApiDrive(
            [
                'description' => $description,
                'name' => $name,
                'quota' => new Quota(['total' => $quota])
            ]
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
                $this->accessToken
            );
        }
        throw new InvalidResponseException(
            "Drive could not be created. '" .
            $newlyCreatedDrive->getError()->getMessage() .
            "'"
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
     */
    public function getGroups(
        string $search = "",
        OrderDirection $orderBy = OrderDirection::ASC,
        bool $expandMembers = false
    ) {
        $apiInstance = new GroupsApi($this->guzzle, $this->graphApiConfig);
        $orderByString = $orderBy->value === OrderDirection::ASC->value ? "displayName" : "displayName desc";
        try {
            $allGroupsList = $apiInstance->listGroups(
                $search,
                [$orderByString],
                [],
                $expandMembers ? ["members"] : null
            );
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($allGroupsList instanceof OdataError) {
            throw new InvalidResponseException(
                "listGroups returned an OdataError - " . $allGroupsList->getError()
            );
        }
        $apiGroups = $allGroupsList->getValue() ?? [];
        $groupList = [];
        foreach ($apiGroups as $group) {
            $newGroup = new Group(
                $group,
                $this->serviceUrl,
                $this->connectionConfig,
                $this->accessToken
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
     */
    public function getResourceById(string $fileId): OcisResource
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->getServiceUrl() . '/dav/spaces/']);
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        try {
            $properties = [];
            foreach (ResourceMetadata::cases() as $property) {
                $properties[] = $property->value;
            }
            $responses = $webDavClient->propFind(rawurlencode($fileId), $properties);
            $resource = new OcisResource(
                $responses,
                null,
                $this->connectionConfig,
                $this->serviceUrl,
                $this->accessToken
            );
        } catch (SabreClientHttpException|SabreClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        // make sure there is again an element with index 0
        return $resource;
    }

    /**
     * retrieve users known by the system
     *
     * @param string|null $search
     * @return array<User>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function getUsers(?string $search = null): array
    {
        $users = [];
        $apiInstance = new UsersApi(
            $this->guzzle,
            $this->graphApiConfig
        );
        try {
            $collectionOfApiUsers = $apiInstance->listUsers($search);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($collectionOfApiUsers instanceof OdataError) {
            throw new InvalidResponseException(
                "listUsers returned an OdataError - " . $collectionOfApiUsers->getError()
            );
        }
        $apiUsers = $collectionOfApiUsers->getValue() ?? [];
        foreach ($apiUsers as $apiUser) {
            $users[] = new User($apiUser);
        }
        return $users;
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
     */
    public function getNotifications(): array
    {
        try {
            $response = $this->guzzle->get(
                $this->serviceUrl . $this->notificationsEndpoint
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
                'Notification response is invalid. Content: "' .  $content . '"'
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
                    'Id is invalid or missing in notification response. Content: "' . $content . '"'
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
                }
            }
            $notificationContent->{'messageRichParameters'} =
                (isset($ocsData["messageRichParameters"])) ? $ocsData["messageRichParameters"] : [];

            $notifications[] = new Notification(
                $this->accessToken,
                $this->connectionConfig,
                $this->serviceUrl,
                $id,
                $notificationContent
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
    */
    public function createGroup(string $groupName, string $description = ""): Group
    {
        $apiInstance = new GroupsApi($this->guzzle, $this->graphApiConfig);
        $group = new OpenAPIGrop(["display_name" => $groupName, "description" => $description]);
        try {
            $newlyCreatedGroup = $apiInstance->createGroup($group);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($newlyCreatedGroup instanceof OdataError) {
            throw new InvalidResponseException(
                "createGroup returned an OdataError - " . $newlyCreatedGroup->getError()
            );
        }
        return new Group(
            $newlyCreatedGroup,
            $this->serviceUrl,
            $this->connectionConfig,
            $this->accessToken
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
     */
    public function deleteGroupByID(string $groupId): void
    {
        $apiInstance = new GroupApi($this->guzzle, $this->graphApiConfig);
        try {
            $apiInstance->deleteGroup($groupId);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }

    /**
     * @return array<ShareCreated>
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws HttpException
     * @throws InvalidResponseException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function getSharedByMe(): array
    {
        $apiInstance = new MeDriveApi(
            $this->guzzle,
            $this->graphApiConfig
        );
        try {
            $shareList = $apiInstance->listSharedByMe();
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        if ($shareList instanceof OdataError) {
            throw new InvalidResponseException(
                "listSharedByMe returned an OdataError - " . $shareList->getError()
            );
        }
        if ($shareList->getValue() === null) {
            throw new InvalidResponseException(
                "listSharedByMe returned 'null' where an array of data were expected"
            );
        }
        $shares = [];
        foreach ($shareList->getValue() as $share) {
            $resourceId = empty($share->getId()) ?
                throw new InvalidResponseException(
                    "Invalid resource id '" . print_r($share->getId(), true) . "'"
                )
                : (string)$share->getId();

            $driveId = empty($share->getParentReference()) || empty($share->getParentReference()->getDriveId()) ?
                throw new InvalidResponseException(
                    "Invalid driveId '" . print_r($share->getParentReference(), true) . "'"
                )
                : (string)$share->getParentReference()->getDriveId();

            if (!is_iterable($share->getPermissions())) {
                throw new InvalidResponseException("Invalid permissions provided");
            }
            foreach ($share->getPermissions() as $apiSharePermission) {

                $shares[] = new ShareCreated(
                    $apiSharePermission,
                    $resourceId,
                    $driveId,
                    $this->connectionConfig,
                    $this->serviceUrl,
                    $this->accessToken
                );
            }
        }
        return $shares;
    }
}
