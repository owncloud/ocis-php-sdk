<?php
require_once(__DIR__ . '/../vendor/autoload.php');

use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\Api\MeDrivesApi;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Quota;
use OpenAPI\Client\Model\Drive as ApiDrive;

abstract class OrderDirection {
    const ASC = "asc";
    const DESC = "desc";

    public static function isOrderDirectionValid(?string $direction): bool {
        $reflector = new ReflectionClass('OrderDirection');
        if (!in_array($direction, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}

abstract class DriveOrder {
    const LASTMODIFIED = "lastModifiedDateTime";
    const NAME = "name";

    public static function isOrderValid(?string $order): bool {
        $reflector = new ReflectionClass('DriveOrder');
        if (!in_array($order, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}

class DriveType {
    const PROJECT = "project";
    const PERSONAL = "personal";
    const VIRTUAL = "virtual";

    public static function isTypeValid(?string $type): bool {
        $reflector = new ReflectionClass('DriveType');
        if (!in_array($type, array_merge([null], $reflector->getConstants()))) {
            return false;
        }
        return true;
    }
}

class Drive {
    private ApiDrive $apiDrive;

    public function __construct(ApiDrive $apiDrive) {
        $this->apiDrive = $apiDrive;
    }

    /**
     * @return string
     */
    public function getAlias(): string {
        return (string)$this->apiDrive->getDriveAlias();
    }

    /**
     * @return string
     */
    public function getType(): string {
        return (string)$this->apiDrive->getDriveType();
    }

    /**
     * @return string
     */
    public function getId(): string {
        return (string)$this->apiDrive->getId();
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    public function getLastModifiedDateTime(): DateTime {
        $date = $this->apiDrive->getLastModifiedDateTime();
        if ($date instanceof DateTime) {
            return $date;
        }
        throw new Exception(
            'invalid LastModifiedDateTime returned: "' . print_r($date, true) . '"'
        );
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->apiDrive->getName();
    }

    /**
     * @return Quota
     * @throws Exception
     */
    public function getQuota(): Quota {
        $quota = $this->apiDrive->getQuota();
        if ($quota instanceof Quota) {
            return $quota;
        }
        throw new Exception(
            'invalid quota returned: "' . print_r($quota, true) . '"'
        );
    }

    /**
     * @return stdClass
     */
    public function getRawData(): stdClass {
        return $this->apiDrive->jsonSerialize();
    }

    public function delete(): void {
        // alias to disable(), but can only happen if the space is already disabled
        throw new Exception("This function is not implemented yet.");
    }

    public function disable(): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function setName(string $name): Drive {
        throw new Exception("This function is not implemented yet.");
    }

    public function setQuota(int $quota): Drive {
        throw new Exception("This function is not implemented yet.");
    }

    public function setDescription(string $description): Drive {
        throw new Exception("This function is not implemented yet.");
    }

    public function setImage(GdImage $image): Drive {
        // upload image to dav/spaces/<space-id>/.space/<image-name>
        // PATCH space
        throw new Exception("This function is not implemented yet.");
    }

    public function setReadme(string $readme): Drive {
        // upload content of $readme to dav/spaces/<space-id>/.space/readme.md
        throw new Exception("This function is not implemented yet.");
    }

    /**
     * list the content of that path
     * @param string $path
     * @return array<string>
     */
    public function listResources(string $path = "/"): array {
        throw new Exception("This function is not implemented yet.");
    }

    public function getFile(string $path): resource {
        throw new Exception("This function is not implemented yet.");
    }

    public function getFileById(string $fileId): resource {
        throw new Exception("This function is not implemented yet.");
    }

    public function createFolder(string $path): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function getResourceMetadata(string $path = "/"): stdClass {
        throw new Exception("This function is not implemented yet.");
    }

    public function getResourceMetadataById(string $id): stdClass {
        throw new Exception("This function is not implemented yet.");
    }

    public function uploadFile(string $path, string $content): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function uploadFileStream(string $path, resource $resource): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function deleteResource(string $path): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function moveResource(string $srcPath, string $destPath, Drive $destDrive = null): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function tagResource(string $path, array $tags): void {
        throw new Exception("This function is not implemented yet.");
    }

    public function untagResource(string $path, array $tags): void {
        throw new Exception("This function is not implemented yet.");
    }
}

class Ocis {
    private string $serviceUrl;
    private string $accessToken;
    private DrivesGetDrivesApi $apiInstance;
    private Configuration $graphApiConfig;
    private GuzzleHttp\Client $guzzle;

    public function __construct(
        string $serviceUrl, string $accessToken
    ) {
        $this->serviceUrl = $serviceUrl;
        $this->accessToken = $accessToken;
        $this->guzzle = new GuzzleHttp\Client(['headers' => ['Authorization' => 'Bearer ' . $accessToken]]);
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
     * @throws Exception
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
     * @throws Exception
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
            throw new InvalidArgumentException('$orderBy is invalid');
        }
        if (!OrderDirection::isOrderDirectionValid($orderDirection)) {
            throw new InvalidArgumentException('$orderDirection is invalid');
        }
        return $orderBy . ' ' . $orderDirection;
    }

    private function getListDrivesFilterString(
        string $type = null
    ): ?string {
        if (!DriveType::isTypeValid($type)) {
            throw new InvalidArgumentException('$type is invalid');
        }

        if ($type !== null) {
            $filter = 'driveType eq \'' . $type . '\'';
        } else {
            $filter = null;
        }
        return $filter;
    }

    /**
     * @throws Exception
     */
    public function getDriveById(string $driveId): Drive {
        throw new Exception("This function is not implemented yet.");
    }

    /**
     * @throws Exception
     */
    public function createDrive(string $name, int $quota = null, string $description = null): Drive {
        throw new Exception("This function is not implemented yet.");
    }

}
