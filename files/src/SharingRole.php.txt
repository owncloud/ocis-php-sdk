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
    private ?string $id;
    private ?string $displayName;
    private ?string $description;
    private ?int $weight;

    /**
     * @ignore The developer using the SDK does not need to create SharingRole objects manually,
     *         but should use OcisResource::getRoles() to query for possible roles for the given resource
     */
    public function __construct(UnifiedRoleDefinition $apiRole)
    {
        $this->id = $apiRole->getId();
        $this->displayName = $apiRole->getDisplayName();
        $this->description = $apiRole->getDescription();
        $this->weight = $apiRole->getAtLibreGraphWeight();
    }
    /**
     * @param string|null|int $data
     * @param string $dataKey
     *
     * @throws InvalidResponseException
     * @return string
     */
    public function validateData($data, $dataKey): string
    {
        return ($data === null || $data === '') ?
        throw new InvalidResponseException(
            "Invalid $dataKey returned for user '" . print_r($data, true) . "'",
        ) : (string)$data;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->validateData($this->id, "id");
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->validateData($this->displayName, "display name");
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->validateData($this->description, "description");
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return (int)$this->validateData($this->weight, "weight");
    }


}
