<?php

namespace Owncloud\OcisSdkPhp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\OdataError;

class Ocis
{
    private string $serviceUrl;
    private string $accessToken;
    private ?DrivesApi $drivesApiInstance = null;
    private ?DrivesGetDrivesApi $drivesGetDrivesApiInstance = null;
    private Configuration $graphApiConfig;
    private Client $guzzle;
    private string $notificationsEndpoint = '/ocs/v2.php/apps/notifications/api/v1/notifications?format=json';

    /**
     * @phpstan-var array{'headers'?:array<string, mixed>, 'verify'?:bool}
     */
    private array $connectionConfig;

    /**
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     *        valid config keys are: headers, verify
     *        headers has to be an array in the form like
     *        [
     *            'User-Agent' => 'testing/1.0',
     *            'Accept'     => 'application/json',
     *            'X-Foo'      => ['Bar', 'Baz']
     *        ]
     *        verify is a boolean to disable SSL checking
     * @throws \Exception
     */
    public function __construct(
        string $serviceUrl,
        string $accessToken,
        array $connectionConfig = []
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new Client(self::createGuzzleConfig($connectionConfig, $this->accessToken));
        $this->connectionConfig = $connectionConfig;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost($serviceUrl . '/graph/v1.0');

    }

    public function setGuzzle(Client $guzzle): void
    {
        $this->guzzle = $guzzle;
    }

    /**
     * @param array<mixed> $connectionConfig
     */
    public static function isConnectionConfigValid(array $connectionConfig): bool
    {
        $validConnectionConfigKeys = ['headers' => [], 'verify' => true];
        foreach ($connectionConfig as $key => $value) {
            if (!array_key_exists($key, $validConnectionConfigKeys)) {
                return false;
            }
        }
        if (array_key_exists('verify', $connectionConfig) && !is_bool($connectionConfig['verify'])) {
            return false;
        }
        if (array_key_exists('headers', $connectionConfig)) {
            if (!is_array($connectionConfig['headers'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * combines passed in config settings for guzzle with the default settings needed
     * for the class and returns the complete array
     *
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @return array<string, mixed>
     * @throws \Exception
     */
    public static function createGuzzleConfig(array $connectionConfig, string $accessToken): array
    {
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \Exception('connection configuration not valid');
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

    public function setDrivesApiInstance(DrivesApi|null $apiInstance): void
    {
        $this->drivesApiInstance = $apiInstance;
    }

    public function setDrivesGetDrivesApiInstance(DrivesGetDrivesApi|null $apiInstance): void
    {
        $this->drivesGetDrivesApiInstance = $apiInstance;
    }

    /**
     * @throws \Exception
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->guzzle = new Client(Ocis::createGuzzleConfig($this->connectionConfig, $accessToken));
        $this->drivesApiInstance = null;
        $this->drivesGetDrivesApiInstance = null;
    }

    /**
     * Get all available drives
     *
     * @return array<Drive>
     * @throws \Exception
     */
    public function listAllDrives(
        string $orderBy = DriveOrder::NAME,
        string $orderDirection = OrderDirection::ASC,
        string $type = null
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

        /** @phan-suppress-next-line PhanTypeMismatchArgumentNullable */
        $allDrivesList = $apiInstance->listAllDrives($order, $filter);
        if ($allDrivesList instanceof OdataError) {
            // ToDo: understand how this can happen, and what to do about it.
            throw new \Exception("listAllDrives returned an OdataError");
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive($apiDrive, $this->connectionConfig, $this->accessToken);
            $drives[] = $drive;
        }

        return $drives;
    }

    /**
     * Get all drives where the current user is a regular member of
     *
     * @return array<Drive>
     * @throws \Exception
     */
    public function listMyDrives(
        string $orderBy = DriveOrder::NAME,
        string $orderDirection = OrderDirection::ASC,
        string $type = null
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

        /** @phan-suppress-next-line PhanTypeMismatchArgumentNullable */
        $allDrivesList = $apiInstance->listMyDrives($order, $filter);
        if ($allDrivesList instanceof OdataError) {
            // ToDo: understand how this can happen, and what to do about it.
            throw new \Exception("listMyDrives returned an OdataError");
        }
        $apiDrives = $allDrivesList->getValue();
        $apiDrives = $apiDrives ?? [];
        foreach ($apiDrives as $apiDrive) {
            $drive = new Drive($apiDrive, $this->connectionConfig, $this->accessToken);
            $drives[] = $drive;
        }
        return $drives;
    }

    private function getListDrivesOrderString(
        string $orderBy = DriveOrder::NAME,
        string $orderDirection = OrderDirection::ASC
    ): string {
        if (!DriveOrder::isOrderValid($orderBy)) {
            throw new \InvalidArgumentException('$orderBy is invalid');
        }
        if (!OrderDirection::isOrderDirectionValid($orderDirection)) {
            throw new \InvalidArgumentException('$orderDirection is invalid');
        }
        return $orderBy . ' ' . $orderDirection;
    }

    private function getListDrivesFilterString(
        string $type = null
    ): ?string {
        if (!DriveType::isTypeValid($type)) {
            throw new \InvalidArgumentException('$type is invalid');
        }

        if ($type !== null) {
            $filter = 'driveType eq \'' . $type . '\'';
        } else {
            $filter = null;
        }
        return $filter;
    }

    /**
     * @throws \Exception
     */
    public function getDriveById(string $driveId): Drive
    {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * @param int $quota in bytes
     * @return Drive
     * @throws \Exception
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws \InvalidArgumentException
     */
    public function createDrive(
        string $name,
        int $quota = 0,
        string $description = null
    ): Drive {
        if ($quota < 0) {
            throw new \InvalidArgumentException('quota cannot be less than 0');
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
                'quota' => ['total' => $quota]
            ]
        );
        try {
            $newlyCreatedDrive = $apiInstance->createDrive($apiDrive);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        if ($newlyCreatedDrive instanceof ApiDrive) {
            return new Drive($newlyCreatedDrive, $this->connectionConfig, $this->accessToken);
        }
        throw new \Exception(
            "Drive could not be created. '" .
            $newlyCreatedDrive->getError()->getMessage() .
            "'"
        );
    }

    /**
     * @throws \Exception
     * @return array<Notification>
     */
    public function getNotifications(): array
    {
        try {
            $response = $this->guzzle->get(
                $this->serviceUrl . $this->notificationsEndpoint
            );
        } catch (GuzzleException|ClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        $content = $response->getBody()->getContents();
        $ocsResponse = json_decode($content, true);
        if (!is_array($ocsResponse)) {
            throw new \Exception(
                'Could not decode notification response. Content: "' .  $content . '"'
            );
        }
        if (
            !isset($ocsResponse['ocs']) ||
            !array_key_exists('data', $ocsResponse['ocs']) ||
            !is_array($ocsResponse['ocs']['data']) &&
            !is_null($ocsResponse['ocs']['data'])
        ) {
            throw new \Exception(
                'Notification response is invalid. Content: "' .  $content . '"'
            );
        }
        if (is_null($ocsResponse['ocs']['data'])) {
            $ocsResponse['ocs']['data'] = [];
        }
        $notifications = [];
        foreach ($ocsResponse['ocs']['data'] as $notificationContent) {
            if (
                !isset($notificationContent["notification_id"]) ||
                !is_string($notificationContent["notification_id"]) ||
                $notificationContent["notification_id"] === "") {
                throw new \Exception(
                    'Id is invalid or missing in notification response. Content: "' . $content . '"'
                );
            }
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
                    "messageRichParameters"
                ] as $key
            ) {
                if (!isset($notificationContent[$key])) {
                    if ($key === "messageRichParameters") {
                        $notificationContent[$key] = [];
                    } else {
                        $notificationContent[$key] = "";
                    }
                }
            }
            $notifications[] = new Notification(
                $this->accessToken,
                $this->connectionConfig,
                $this->serviceUrl,
                $notificationContent["notification_id"],
                $notificationContent["app"],
                $notificationContent["user"],
                $notificationContent["datetime"],
                $notificationContent["object_id"],
                $notificationContent["object_type"],
                $notificationContent["subject"],
                $notificationContent["subjectRich"],
                $notificationContent["message"],
                $notificationContent["messageRich"],
                $notificationContent["messageRichParameters"]
            );
        }
        return $notifications;
    }
}
