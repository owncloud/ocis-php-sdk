<?php
namespace Owncloud\OcisSdkPhp;

require_once(__DIR__ . '/../vendor/autoload.php');

use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\Configuration;

class Ocis {
    private string $serviceUrl;
    private string $accessToken;
    private DrivesGetDrivesApi $apiInstance;
    private Configuration $graphApiConfig;
    private \GuzzleHttp\Client $guzzle;

    public function __construct(
        string $serviceUrl, string $accessToken
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new \GuzzleHttp\Client(['headers' => ['Authorization' => 'Bearer ' . $accessToken]]);
        $this->graphApiConfig = Configuration::getDefaultConfiguration()->setHost($serviceUrl . '/graph/v1.0');
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
     * @throws \Exception
     */
    public function createDrive(string $name, int $quota = null, string $description = null): Drive {
        throw new \Exception("This function is not implemented yet.");
    }

}
