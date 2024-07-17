<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Permission as ApiPermission;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

/**
 * Parent class representing different types of share objects
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class Share
{
    protected string $accessToken;
    /**
     * @phpstan-var ConnectionConfig
     */
    protected array $connectionConfig;
    protected string $serviceUrl;
    protected Configuration $graphApiConfig;
    protected ApiPermission $apiPermission;
    protected string $driveId;
    protected string $resourceId;


    /**
     * @phpstan-param ConnectionConfig $connectionConfig
     * @ignore The developer using the SDK does not need to create share objects manually,
     *         but should use the OcisResource/Drive class to invite people to a resource/drive and
     *         that will create DriveShare/ResourceShareCreated objects
     */
    public function __construct(
        ApiPermission $apiPermission,
        string        $driveId,
        array         $connectionConfig,
        string        $serviceUrl,
        string        &$accessToken
    ) {
        $this->apiPermission = $apiPermission;
        $this->driveId = $driveId;

        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');

        $this->connectionConfig = $connectionConfig;
    }

    public function getPermissionId(): string
    {
        $id = $this->apiPermission->getId();
        if ($id === null || $id === '') {
            throw new InvalidResponseException(
                "Invalid id returned for permission '" . print_r($id, true) . "'"
            );
        }
        return $id;
    }

    public function getExpiration(): ?\DateTimeImmutable
    {
        $expiry = $this->apiPermission->getExpirationDateTime();
        if ($expiry === null) {
            return null;
        }

        return \DateTimeImmutable::createFromMutable($expiry);
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }
}
