<?php

abstract class OrderDirection {
    const ASC = "asc";
    const DESC = "desc";
}

abstract class DriveType {
    const PROJECT = "project";
    const PERSONAL = "personal";
    const VIRTUAL = "virtual";
}

abstract class DriveOrder {
    const LASTMODIFIED = "lastModifiedDateTime";
    const NAME = "name";
}

class Quota {
    private int $remaining;
    private string $state;
    private int $total;
    private int $used;
}

class Drive {
    private string $alias;
    private DriveType $type;
    private string $id;
    private DateTime $lastModifiedDateTime;
    private string $name;
    private Quota $quota;
    private stdClass $rawData; //object from the raw JSON data

    /**
     * @return string
     */
    public function getAlias(): string {
        return $this->alias;
    }

    /**
     * @return DriveType
     */
    public function getType(): DriveType {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getLastModifiedDateTime(): DateTime {
        return $this->lastModifiedDateTime;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return Quota
     */
    public function getQuota(): Quota {
        return $this->quota;
    }

    /**
     * @return stdClass
     */
    public function getRawData(): stdClass {
        return $this->rawData;
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
    public function __construct(
        string $serviceUrl, string $accessToken
    ) {
        $this->serviceUrl=$serviceUrl;
        $this->accessToken=$accessToken;
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
    public function listAllDrives(DriveOrder $orderby, OrderDirection $order, DriveType $type): array {
        throw new Exception("This function is not implemented yet.");
    }

    /**
     * Get all drives where the current user is a regular member of
     *
     * @return array<Drive>
     * @throws Exception
     */
    public function listMyDrives(DriveOrder $orderby, OrderDirection $order, DriveType $type): array {
        throw new Exception("This function is not implemented yet.");
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
