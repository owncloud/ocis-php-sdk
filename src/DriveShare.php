<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Permission as ApiPermission;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

class DriveShare
{
    private ApiPermission $apiPermission;
    private string $driveId;


    /**
     * @ignore The developer using the SDK does not need to create share objects manually,
     *         but should use the OcisResource class to invite people to a resource and
     *         that will create ShareCreated objects
     */
    public function __construct(
        ApiPermission $apiPermission,
        string        $driveId
    ) {
        $this->apiPermission = $apiPermission;
        $this->driveId = $driveId;
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
        } else {
            return \DateTimeImmutable::createFromMutable($expiry);
        }

    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }
}
