<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

/**
 * class to define a role of a user or group in a share
 * every role contains specific permissions
 */
class SharingRole
{
    private string $id;
    private string $displayName;
    private string $description;
    private int $weight;

    /**
     * @ignore The developer using the SDK does not need to create SharingRole objects manually,
     *         but should use OcisResource::getRoles() to query for possible roles for the given resource
     */
    public function __construct(UnifiedRoleDefinition $apiRole)
    {
        $this->id = !is_string($apiRole->getId()) ?
            throw new InvalidResponseException(
                "Invalid id returned for sharing role '" . print_r($apiRole->getId(), true) . "'"
            )
            : (string)$apiRole->getId();
        $this->displayName = !is_string($apiRole->getDisplayName()) ?
            throw new InvalidResponseException(
                "Invalid display name returned for sharing role '" .
                print_r($apiRole->getId(), true) .
                "'"
            )
            : (string)$apiRole->getDisplayName();
        $this->description = !is_string($apiRole->getDescription()) ? "" : $apiRole->getDescription();
        $this->weight = (int)$apiRole->getAtLibreGraphWeight();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }


}
