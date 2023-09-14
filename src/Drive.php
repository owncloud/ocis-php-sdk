<?php

namespace Owncloud\OcisSdkPhp;

use DateTime;
use OpenAPI\Client\Model\Drive as ApiDrive;
use OpenAPI\Client\Model\Quota;
use Sabre\DAV\Client;

class Drive
{
    private ApiDrive $apiDrive;
    private string $accessToken;
    private Client $webDavClient;
    private string $webDavUrl = '';

    public function __construct(ApiDrive $apiDrive, &$accessToken)
    {
        $this->apiDrive = $apiDrive;
        $this->accessToken = &$accessToken;

        $this->webDavClient = new Client([
            'baseUri' => $this->getWebDavUrl(),
        ]);
        $this->webDavClient->addCurlSetting(CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
        $this->webDavClient->addCurlSetting(CURLOPT_XOAUTH2_BEARER, $accessToken);
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
     * @return object
     */
    public function getRoot():object
    {
        return $this->apiDrive->getRoot();
    }

    /**
     * @return string
     */
    public function getWebDavUrl():string
    {
        if (empty($this->webDavUrl)) {
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

    public function getFile(string $path)
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function getFileById(string $fileId)
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function createFolder(string $path): bool
    {
        $response = $this->webDavClient->request('MKCOL', ltrim($path,"/"));
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
     * upload file with content
     * update file content if file already exist
     *
     * @param string $path
     * @param string $content
     *
     * @return bool
     * @throws Exception
     */
    public function uploadFile(string $path, string $content): bool
    {
        $response = $this->webDavClient->request('PUT', ltrim($path,"/"), $content);

        if (($response["statusCode"] !== 201) && ($response["statusCode"] !== 204)) {
            throw new \Exception("Failed to upload file $path. The request returned a status code of $response[statusCode]");
        }
        return true;
    }

    public function uploadFileStream(string $path, $resource): void
    {
        throw new \Exception("This function is not implemented yet.");
    }

    public function deleteResource(string $path): void
    {
        throw new \Exception("This function is not implemented yet.");
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
