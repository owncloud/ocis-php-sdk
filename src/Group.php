<?php

namespace Owncloud\OcisPhpSdk;

class Group
{
    public function __construct(
        private string $id,
        private string $description = '',
        private string $displayName,
        private array $groupTypes = [],
        private array $members = [],
        private array $membersodatabind = [],
    ) {}

    /**
     * Get the value of id
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the value of description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the value of displayName
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get the value of groupTypes
     */
    public function getGroupTypes(): array
    {
        return $this->groupTypes;
    }

    /**
     * Get the value of members
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * Get the value of membersodatabind
     */
    public function getMembersodatabind(): array
    {
        return $this->membersodatabind;
    }
}
