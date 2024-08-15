<?php

namespace Owncloud\OcisPhpSdk;

use  OpenAPI\Client\Model\User;
use  OpenAPI\Client\Model\EducationUser;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

class BaseUser
{
    private ?string $id;
    private ?string $displayName;
    private ?string $mail;
    private ?string $onPremisesSamAccountName;

    /**
     * @param User|EducationUser $user
     * @ignore The developer using the SDK does not need to create User objects manually,
     *         but should use the Ocis class to query the server for users
     */
    public function __construct(
        User|EducationUser $user,
    ) {
        $this->id = $user->getId();
        $this->displayName = $user->getDisplayName();
        $this->mail = $user->getMail();
        $this->onPremisesSamAccountName = $user->getOnPremisesSamAccountName();
    }

    /**
     * Get the value of displayName
     */
    public function getDisplayName(): string
    {
        return (($this->displayName === null) || ($this->displayName === '')) ?
        throw new InvalidResponseException(
            "Invalid displayName returned for user '" . print_r($this->displayName, true) . "'",
        )
        : (string) $this->displayName;
    }

    /**
     * Get the value of id
     */
    public function getId(): string
    {
        return (($this->id === null) || ($this->id === '')) ?
        throw new InvalidResponseException(
            "Invalid id returned for user '" . print_r($this->id, true) . "'",
        ) : (string) $this->id;
    }

    /**
     * Get the value of email
     */
    public function getMail(): string
    {
        return empty($this->mail) ?
        throw new InvalidResponseException(
            "Invalid mail returned for user '" . print_r($this->mail, true) . "'",
        )
        : (string) $this->mail;
    }

    /**
     * Get the value of onPremisesSamAccountName
     */
    public function getOnPremisesSamAccountName(): string|null
    {
        return $this->onPremisesSamAccountName;
    }
}
