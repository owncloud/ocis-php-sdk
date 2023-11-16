<?php

namespace Owncloud\OcisPhpSdk;

use \OpenAPI\Client\Model\Permission as ApiPermission;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotImplementedException;

/**
 * A permission to a resource
 */
class SharingLink
{
    private string $permissionId;
    private ?LinkType $type;
    private ?string $webUrl;
    private ?string $displayName;


    /**
     * @throws InvalidResponseException
     */
    public function __construct(ApiPermission $apiPermission)
    {
        $this->permissionId = !is_string($apiPermission->getId()) ?
            throw new InvalidResponseException(
                "Invalid id returned for permission '" . print_r($apiPermission->getId(), true) . "'"
            )
            : $apiPermission->getId();
        $this->displayName = $apiPermission->getLink()->getAtLibreGraphDisplayName();
        $this->webUrl = $apiPermission->getLink()->getWebUrl(); // check for null
        $this->type = LinkType::tryFrom($apiPermission->getLink()->getType()); //todo check for null
    }

    public function getPermissionId(): string
    {
        return $this->permissionId;
    }

    /**
     * @todo This function is not implemented yet! Place, name and signature of the function might change!
     */
    public function delete(): bool
    {
        throw new NotImplementedException(Ocis::FUNCTION_NOT_IMPLEMENTED_YET_ERROR_MESSAGE);
    }

}
