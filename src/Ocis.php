<?php

namespace Owncloud\OcisSdkPhp;

require_once(__DIR__ . '/../vendor/autoload.php');

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;

class Ocis
{
    private string $serviceUrl;
    private string $accessToken;
    /**
     * @var DrivesApi|DrivesGetDrivesApi|null
     */
    private $apiInstance = null;
    private Configuration $graphApiConfig;
    private \GuzzleHttp\Client $guzzle;
    private $notificationsEndpoint = '/ocs/v2.php/apps/notifications/api/v1/notifications?format=json';

    public function __construct(
        string $serviceUrl,
        string $accessToken,
        $guzzleConfig = []
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new \GuzzleHttp\Client($this->createGuzzleConfig($guzzleConfig));

        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost($serviceUrl . '/graph/v1.0');
    }

    public function setGuzzle(\GuzzleHttp\Client $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    /**
     * combines passed in config settings for guzzle with the default settings needed
     * for the class and returns the complete array
     *
     * @param $guzzleConfig
     * @return array<mixed>
     */
    public function createGuzzleConfig($guzzleConfig = []): array
    {
        if (!isset($guzzleConfig['headers'])) {
            $guzzleConfig['headers'] = [];
        }
        $guzzleConfig['headers'] = array_merge(
            $guzzleConfig['headers'],
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );
        return $guzzleConfig;
    }

    public function setApiInstance(DrivesGetDrivesApi|MeDrivesApi|DrivesApi|null $apiInstance): void
    {
        $this->apiInstance = $apiInstance;
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
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
        if ($this->apiInstance === null) {
            $apiInstance = new DrivesGetDrivesApi(
                $this->guzzle,
                $this->graphApiConfig
            );
        } else {
            $apiInstance = $this->apiInstance;
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
        foreach ($apiInstance->listAllDrives($order, $filter)->getValue() as $apiDrive) {
            $drive = new Drive($apiDrive, $this->accessToken);
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
        foreach ($apiInstance->listMyDrives($order, $filter)->getValue() as $apiDrive) {
            $drive = new Drive($apiDrive, $this->accessToken);
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
     * @param string $name
     * @param int $quota in bytes
     * @param string|null $description
     * @return Drive
     * @throws  \Exception
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
        if ($this->apiInstance === null) {
            $apiInstance = new DrivesApi(
                $this->guzzle,
                $this->graphApiConfig
            );
        } else {
            $apiInstance = $this->apiInstance;
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
            return new Drive($newlyCreatedDrive, $this->accessToken);
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
        if ($ocsResponse === null) {
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
                    'Id is invalid or missing in notification response is invalid. Content: "' . $content . '"'
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


    /**
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function deleteAllNotifications(): void
    {
        try {
            $this->guzzle->delete(
                $this->serviceUrl . $this->notificationsEndpoint
            );
        } catch (GuzzleException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
