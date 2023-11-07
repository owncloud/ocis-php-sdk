<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Permission;
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
     */
    public function __construct(UnifiedRoleDefinition $apiRole)
    {
        $this->id = !is_string($apiRole->getId()) ?
            throw new InvalidResponseException(
                "Invalid id returned for role '" . print_r($apiRole->getId(), true) . "'"
            )
            : $apiRole->getId();
        $this->displayName = !is_string($apiRole->getDisplayName()) ?
            throw new InvalidResponseException(
                "Invalid display name returned for role '" . print_r($apiRole->getId(), true) . "'"
            )
            : $apiRole->getDisplayName();
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
