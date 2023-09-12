<?php
namespace Owncloud\OcisSdkPhp;

require_once(__DIR__ . '/../vendor/autoload.php');

use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Drive as ApiDrive;

class Ocis {
    private string $serviceUrl;
    private string $accessToken;
    /**
     * @var DrivesApi|DrivesGetDrivesApi|null
     */
    private $apiInstance = null;
    private Configuration $graphApiConfig;
    private \GuzzleHttp\Client $guzzle;

    public function __construct(
        string $serviceUrl, string $accessToken, $guzzleConfig = []
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new \GuzzleHttp\Client($this->createGuzzleConfig($guzzleConfig));

        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost($serviceUrl . '/graph/v1.0');
    }

    /**
     * combines passed in config settings for guzzle with the default settings needed
     * for the class and returns the complete array
     *
     * @param $guzzleConfig
     * @return array<mixed>
     */
    public function createGuzzleConfig($guzzleConfig = []): array {
        if (!isset($guzzleConfig['headers'])) {
            $guzzleConfig['headers'] = [];
        }
        $guzzleConfig['headers'] = array_merge(
            $guzzleConfig['headers'],
            ['Authorization' => 'Bearer ' . $this->accessToken]
        );
        return $guzzleConfig;
    }

    public function setApiInstance(DrivesGetDrivesApi|MeDrivesApi|DrivesApi|null $apiInstance): void {
        $this->apiInstance = $apiInstance;
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken(string $accessToken): void {
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
        $apiInstance = new DrivesGetDrivesApi(
            $this->guzzle,
            $this->graphApiConfig
        );
        $order = $this->getListDrivesOrderString($orderBy, $orderDirection);
        $filter = $this->getListDrivesFilterString($type);
        $drives = [];
        foreach ($apiInstance->listAllDrives($order, $filter)->getValue() as $apiDrive) {
            $drive = new Drive($apiDrive);
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
        string $type = null): array {
        $apiInstance = new MeDrivesApi(
            $this->guzzle,
            $this->graphApiConfig
        );
        $drives = [];
        $order = $this->getListDrivesOrderString($orderBy, $orderDirection);
        $filter = $this->getListDrivesFilterString($type);
        foreach ($apiInstance->listMyDrives($order, $filter)->getValue() as $apiDrive) {
            $drive = new Drive($apiDrive);
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
    public function getDriveById(string $driveId): Drive {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * @param string $name
     * @param int $quota in bytes
     * @param string|null $description
     * @return Drive
     * @throws ApiException
     * @throws \Exception
     */
    public function createDrive(
        string $name, int $quota = 0, string $description = null
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
            if ($e->getCode() === 403) {
                throw new ForbiddenException($e);
            }
            throw $e;
        }

        if ($newlyCreatedDrive instanceof ApiDrive) {
            return new Drive($newlyCreatedDrive);
        }
        throw new \Exception(
            "Drive could not be created. '" .
            $newlyCreatedDrive->getError()->getMessage() .
            "'"
        );
    }

}
