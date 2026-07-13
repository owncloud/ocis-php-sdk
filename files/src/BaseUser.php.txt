<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\User;
use OpenAPI\Client\Model\EducationUser;
use OpenAPI\Client\Model\ObjectIdentity;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

class BaseUser
{
    private ?string $id;
    private ?string $displayName;
    private ?string $mail;
    private ?string $onPremisesSamAccountName;
    private ?string $surname;
    private ?string $givenName;
    /**
     * @var array<ObjectIdentity>|null
     */
    private ?array $identities;

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
        $this->surname = $user->getSurname();
        $this->givenName = $user->getGivenName();
        $this->identities = $user->getIdentities();
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
        : (string)$this->displayName;
    }

    /**
     * Get the value of id
     */
    public function getId(): string
    {
        return (($this->id === null) || ($this->id === '')) ?
        throw new InvalidResponseException(
            "Invalid id returned for user '" . print_r($this->id, true) . "'",
        ) : (string)$this->id;
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
        : (string)$this->mail;
    }

    /**
     * Get the value of onPremisesSamAccountName
     */
    public function getOnPremisesSamAccountName(): string|null
    {
        return $this->onPremisesSamAccountName;
    }

    /**
     * Get the value of surname
     */
    public function getSurname(): string|null
    {
        return $this->surname;
    }

    /**
     * Get the value of givenName
     */
    public function getGivenName(): string|null
    {
        return $this->givenName;
    }

    /**
     * Get the value of identities
     * @return array<ObjectIdentity>|null
     */
    public function getIdentities(): ?array
    {
        return $this->identities;
    }
}
