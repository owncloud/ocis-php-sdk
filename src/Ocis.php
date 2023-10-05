<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\OdataError;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Sabre\HTTP\ResponseInterface;

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
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $serviceUrl,
        string $accessToken,
        array $connectionConfig = []
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new Client(self::createGuzzleConfig($connectionConfig, $this->accessToken));
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
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
     * @throws \InvalidArgumentException
     */
    public static function createGuzzleConfig(array $connectionConfig, string $accessToken): array
    {
        if (!self::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
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
    public function listAllDrives(
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
            $drive = new Drive($apiDrive, $this->connectionConfig, $this->accessToken);
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
    public function listMyDrives(
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
            $drive = new Drive($apiDrive, $this->connectionConfig, $this->accessToken);
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
     * @throws \Exception
     */
    public function getDriveById(string $driveId): Drive
    {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
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
        throw new InvalidResponseException(
            "Drive could not be created. '" .
            $newlyCreatedDrive->getError()->getMessage() .
            "'"
        );
    }

    /**
     * get file content by file id
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function getFileById(string $fileId): string
    {
        $response = $this->getFileResponseInterface($fileId);
        return $response->getBodyAsString();
    }

    /**
     * get file as a file resource
     * @return resource
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    public function getFileStreamById(string $fileId)
    {
        $response = $this->getFileResponseInterface($fileId);
        return $response->getBodyAsStream();
    }

    /**
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     */
    private function getFileResponseInterface(string $fileId): ResponseInterface
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->serviceUrl . '/dav/spaces/']);
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        return $webDavClient->sendRequest("GET", $fileId);
    }

    /**
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
        $ocsResponse = json_decode($content, true);
        if (!is_array($ocsResponse)) {
            throw new InvalidResponseException(
                'Could not decode notification response. Content: "' .  $content . '"'
            );
        }
        if (
            !isset($ocsResponse['ocs']) ||
            !array_key_exists('data', $ocsResponse['ocs']) ||
            !is_array($ocsResponse['ocs']['data']) &&
            !is_null($ocsResponse['ocs']['data'])
        ) {
            throw new InvalidResponseException(
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
                throw new InvalidResponseException(
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
