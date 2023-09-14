<?php

namespace Owncloud\OcisSdkPhp;

use DateTime;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\DriveItem;
use OpenAPI\Client\Model\Quota;
use Sabre\DAV\Client;

class Drive
{
    private ApiDrive $apiDrive;
    private string $accessToken;
    private string $webDavUrl = '';
    private array $connectionConfig;

    public function __construct(ApiDrive $apiDrive, array $connectionConfig, string &$accessToken)
    {
        $this->apiDrive = $apiDrive;
        $this->accessToken = &$accessToken;

        $this->connectionConfig = $connectionConfig;
    }

    /**
     * @throws \Exception
     */
    private function createWebDavClient(): Client
    {
        /**
         * phpstan complains "Offset 'web_dav_url' does not exist on OpenAPI\Client\Model\DriveItem"
         * but it does exist, see vendor/owncloud/libre-graph-api-php/lib/Model/DriveItem.php:83
         */
        /* @phpstan-ignore-next-line */
        $webDavClient = new Client(['baseUri' => (string)($this->apiDrive->getRoot())['web_dav_url']]);
        $curlSettings = $this->createCurlSettings();
        foreach ($curlSettings as $setting => $value) {
            $webDavClient->addCurlSetting($setting, $value);
        }
        return $webDavClient;
    }

    /**
     * @throws \Exception
     */
    public function createCurlSettings(): array
    {
        if (!Ocis::isConnectionConfigValid($this->connectionConfig)) {
            throw new \Exception('connection configuration not valid');
        }
        $settings = [];
        $settings[CURLOPT_HTTPAUTH] = CURLAUTH_BEARER;
        $settings[CURLOPT_XOAUTH2_BEARER] = $this->accessToken;
        if (isset($this->connectionConfig['headers'])) {
            foreach ($this->connectionConfig['headers'] as $header => $value) {
                $settings[CURLOPT_HTTPHEADER][] = $header . ': ' . $value;
            }
        }
        if (isset($this->connectionConfig['verify'])) {
            $settings[CURLOPT_SSL_VERIFYPEER] = $this->connectionConfig['verify'];
            $settings[CURLOPT_SSL_VERIFYHOST] = $this->connectionConfig['verify'];
        }
        return $settings;
    }

    /**
     * mainly for unit tests
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }
    /**
     * @return string
     */
    public function getAlias(): string
    {
        return (string)$this->apiDrive->getDriveAlias();
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return (string)$this->apiDrive->getDriveType();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return (string)$this->apiDrive->getId();
    }

    /**
     * @return ?DriveItem
     */
    public function getRoot(): ?DriveItem
    {
        return $this->apiDrive->getRoot();
    }

    /**
     * @return string
     */
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
     * @return DateTime
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->apiDrive->getName();
    }

    /**
     * @return Quota
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

    /**
     * @return \stdClass
     */
    public function getRawData(): \stdClass
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
     * @return array<string>
     */
    public function listResources(string $path = "/"): array
    {
        throw new \Exception("This function is not implemented yet.");
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
        $response = $webDavClient->request("GET", ltrim($path, "/"));

        if ($response["statusCode"] === 200) {
            return $response['body'];
        }
        throw new \Exception("Failed to retrieve the content of the file $path. The request returned a status code of $response[statusCode]");
    }

    public function getFileById(string $fileId)
    {
        throw new \Exception("This function is not implemented yet.");
    }

    /**
     * @throws \Exception
     */
    public function createFolder(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $response = $webDavClient->request('MKCOL', ltrim($path, "/"));
        if ($response["statusCode"] === 201) {
            return true;
        }
        throw new \Exception("Could not create folder $path. status code $response[statusCode]");
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
     * update file content if file already exist
     * @param string $path
     * @param mixed $resource
     *
     * @return bool
     * @throws \Exception
     */
    private function makePutRequest(string $path, mixed $resource): bool
    {
        $webDavClient = $this->createWebDavClient();
        $response = $webDavClient->request('PUT', ltrim($path, "/"), $resource);
        if (in_array($response['statusCode'], [201,204])) {
            return true;
        }
        throw new \Exception("Failed to upload file $path. The request returned a status code of $response[statusCode]");
    }

    /**
     * upload file with content
     * @param string $path
     * @param string $content
     *
     * @return bool
     * @throws \Exception
     */
    public function uploadFile(string $path, string $content): bool
    {
        return $this->makePutRequest($path, $content);
    }

    /**
     * Uploads a file using streaming
     * @param string $path
     * @param $resource file resource pointing to the file to be uploaded
     *
     * @return bool
     * @throws \Exception
     */
    public function uploadFileStream(string $path, mixed $resource): bool
    {
        if(is_resource($resource)) {
            return $this->makePutRequest($path, $resource);
        }
        throw new \Exception('Provided resource is not valid.');
    }

    /**
     * delete resource
     * @param string $path
     *
     * @return bool
     * @throws \Exception
     */
    public function deleteResource(string $path): bool
    {
        $webDavClient = $this->createWebDavClient();
        $response = $webDavClient->request("DELETE", ltrim($path, "/"));
        if ($response["statusCode"] === 204) {
            return true;
        }
        throw new \Exception("Failed to delete resource $path with status code $response[statusCode]");
    }

    public function moveResource(string $srcPath, string $destPath, Drive $destDrive = null): void
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function tagResource(string $path, array $tags): void
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function untagResource(string $path, array $tags): void
    {
        throw new \Exception("This function is not implemented yet.");
    }
}
