<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Group as OpenApiGroup;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

class Group
{
    private string $id;
    private string $description;
    private string $displayName;
    /**
     * @var array<int,string>
     */
    private array $groupTypes;
    /**
     * @var array<int,User>
     */
    private array $members;
    /**
     * @param OpenApiGroup $openApiGroup
     */
    public function __construct(OpenApiGroup $openApiGroup)
    {
        $this->id = empty($openApiGroup->getId()) ?
        throw new InvalidResponseException(
            "Invalid id returned for group '" . print_r($openApiGroup->getId(), true) . "'"
        )
        : $openApiGroup->getId();
        $this->displayName = empty($openApiGroup->getDisplayName()) ?
        throw new InvalidResponseException(
            "Invalid displayName returned for group '" . print_r($openApiGroup->getDisplayName(), true) . "'"
        )
        : $openApiGroup->getDisplayName();
        $this->description = $openApiGroup->getDescription() ?? "";
        $this->groupTypes = $openApiGroup->getGroupTypes() ?? [];

        $openApiUser = $openApiGroup->getMembers() ?? [];
        $this->members = [];
        foreach ($openApiUser as $user) {
            $this->members[] = new User($user);
        }
    }

    /**
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return array<int,string>
     */
    public function getGroupTypes(): array
    {
        return $this->groupTypes;
    }

    /**
     * @return array<int,User>
     */
    public function getMembers(): array
    {
        return $this->members;
    }

}
