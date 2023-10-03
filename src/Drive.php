<?php

namespace Owncloud\OcisPhpSdk;

use DateTime;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\DriveItem;
use OpenAPI\Client\Model\Quota;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;

class Drive
{
    private ApiDrive $apiDrive;
    private string $accessToken;
    private string $webDavUrl = '';

    /**
     * @phpstan-var array{'headers'?:array<string, mixed>, 'verify'?:bool}
     */
    private array $connectionConfig;

    /**
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @throws \Exception
     */
    public function __construct(ApiDrive $apiDrive, array $connectionConfig, string &$accessToken)
    {
        $this->apiDrive = $apiDrive;
        $this->accessToken = &$accessToken;

        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \Exception('connection configuration not valid');
        }
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * @throws \Exception
     */
    private function createWebDavClient(): WebDavClient
    {
        $webDavClient = new WebDavClient(['baseUri' => $this->getWebDavUrl()]);
        $webDavClient->setCustomSetting($this->connectionConfig, $this->accessToken);
        return $webDavClient;
    }

    /**
     * mainly for unit tests
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getAlias(): string
    {
        return (string)$this->apiDrive->getDriveAlias();
    }

    /**
     * @throws \Exception
     */
    public function getType(): DriveType
    {
        $driveTypeString = (string)$this->apiDrive->getDriveType();
        $driveType = DriveType::tryFrom($driveTypeString);
        if ($driveType instanceof DriveType) {
            return $driveType;
        }
        throw new \Exception(
            'invalid DriveType returned by apiDrive: "' . print_r($driveTypeString, true) . '"'
        );
    }

    public function getId(): string
    {
        return (string)$this->apiDrive->getId();
    }

    public function getRoot(): ?DriveItem
    {
        return $this->apiDrive->getRoot();
    }

    public function getWebUrl(): string
    {
        return (string)$this->apiDrive->getWebUrl();
    }

    public function getWebDavUrl(): string
    {
        if (empty($this->webDavUrl)) {
            /**
             * phpstan complains "Offset 'web_dav_url' does not exist on OpenAPI\Client\Model\DriveItem"
             * but it does exist, see vendor/owncloud/libre-graph-api-php/lib/Model/DriveItem.php:83
             */
            /* @phpstan-ignore-next-line */
            $this->webDavUrl = rtrim((string)($this->getRoot())['web_dav_url'], '/') . '/';
        }
        return $this->webDavUrl;
    }

    /**
     * @throws \Exception
     */
    public function getLastModifiedDateTime(): DateTime
    {
        $date = $this->apiDrive->getLastModifiedDateTime();
        if ($date instanceof DateTime) {
            return $date;
        }
        throw new \Exception(
            'invalid LastModifiedDateTime returned: "' . print_r($date, true) . '"'
        );
    }

    public function getName(): string
    {
        return $this->apiDrive->getName();
    }

    /**
     * @throws \Exception
     */
    public function getQuota(): Quota
    {
        $quota = $this->apiDrive->getQuota();
        if ($quota instanceof Quota) {
            return $quota;
        }
        throw new \Exception(
            'invalid quota returned: "' . print_r($quota, true) . '"'
        );
    }

    public function getRawData(): mixed
    {
        return $this->apiDrive->jsonSerialize();
    }

    public function delete(): void
    {
        // alias to disable(), but can only happen if the space is already disabled
        throw new \Exception("This function is not implemented yet.");
    }

    public function disable(): void
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function setName(string $name): Drive
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function setQuota(int $quota): Drive
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function setDescription(string $description): Drive
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function setImage(\GdImage $image): Drive
    {
        // upload image to dav/spaces/<space-id>/.space/<image-name>
        // PATCH space
        throw new \Exception("This function is not implemented yet.");
    }

    public function setReadme(string $readme): Drive
    {
        // upload content of $readme to dav/spaces/<space-id>/.space/readme.md
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * list the content of that path
     * @param string $path
     *
     * @return array<OcisResource>
     */
    public function listResources(string $path = "/"): array
    {
        $resources = [];
        $webDavClient = $this->createWebDavClient();
        try {
            $responses = $webDavClient->propFind(rawurlencode(ltrim($path, "/")), [], 1);
            foreach ($responses as $response) {
                $resources[] = new OcisResource($response);
            }
            unset($resources[0]);
        } catch (SabreClientHttpException|SabreClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }

        return $resources;
    }

    /**
     * get file content
     * @param string $path
     *
     * @return mixed
     * @throws \Exception
     */
    public function getFile(string $path)
    {
        $webDavClient = $this->createWebDavClient();
        return $webDavClient->sendRequest("GET", rawurlencode(ltrim($path, "/")))->getBody();
    }

    /**
     * get file as a file resource
     * @param string $path
     *
     * @return mixed ocisFile
     * @throws \Exception
     */
    public function getFileStream(string $path): mixed
    {
        $webDavClient = $this->createWebDavClient();
        return $webDavClient->sendRequest("GET", $this->webDavUrl . rawurlencode(ltrim($path, "/")))->getBodyAsStream();
    }

    /**
     * @throws \Exception
     */
    public function createFolder(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('MKCOL', rawurlencode(ltrim($path, "/")));
        return true;
    }

    public function getResourceMetadata(string $path = "/"): \stdClass
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function getResourceMetadataById(string $id): \stdClass
    {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * update file content if the file already exists
     * @param string $path
     * @param resource|string|null $resource
     *
     * @throws \Exception
     */
    private function makePutRequest(string $path, $resource): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('PUT', rawurlencode(ltrim($path, "/")), $resource);
        return true;
    }

    /**
     * upload file with content
     *
     * @throws \Exception
     */
    public function uploadFile(string $path, string $content): bool
    {
        return $this->makePutRequest($path, $content);
    }

    /**
     * Uploads a file using streaming
     * @param resource|string|null $resource file resource pointing to the file to be uploaded
     *
     * @return bool
     * @throws \Exception
     */
    public function uploadFileStream(string $path, $resource): bool
    {
        if (is_resource($resource)) {
            return $this->makePutRequest($path, $resource);
        }
        throw new \Exception('Provided resource is not valid.');
    }

    /**
     * delete resource
     *
     * @throws \Exception
     */
    public function deleteResource(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest("DELETE", rawurlencode(ltrim($path, "/")));
        return true;
    }

    /**
     *  move or rename resource
     *
     * @param string $sourcePath
     * @param string $destinationPath
     *
     * @return bool
     */
    public function moveResource(string $sourcePath, string $destinationPath): bool
    {
        $webDavClient = $this->createWebDavClient();
        $destinationUrl = $this->webDavUrl . rawurlencode(ltrim($destinationPath, "/"));
        $webDavClient->sendRequest('MOVE', "$sourcePath", null, ['Destination' => "$destinationUrl"]);
        return true;
    }

    /**
     * empty trash-bin
     *
     * @return bool
     */
    public function emptyTrashbin(): bool
    {
        $webDavClient = $this->createWebDavClient();
        $webDavClient->sendRequest('DELETE', "/dav/spaces/trash-bin/" . $this->getId());
        return true;
    }

    /**
     * @param array<mixed> $tags
     * @throws \Exception
     */
    public function tagResource(string $path, array $tags): void
    {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * @param array<mixed> $tags
     * @throws \Exception
     */
    public function untagResource(string $path, array $tags): void
    {
        throw new \Exception("This function is not implemented yet.");
    }
}
