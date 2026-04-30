<?php

namespace Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\DriveItem;
use OpenAPI\Client\Model\Identity;
use OpenAPI\Client\Model\RemoteItem;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

/**
 * Ensures that the return type is correct, but Phan does not recognize it.
 * @phan-file-suppress PhanTypeMismatchReturnNullable
 */
class ShareReceived
{
    private DriveItem $shareReceived;


    /**
     * @param DriveItem $shareReceived
     */
    public function __construct(
        DriveItem $shareReceived,
    ) {
        $this->shareReceived = $shareReceived;
    }

    /**
     * @throws InvalidResponseException
     */
    public function getId(): string
    {
        return empty($this->shareReceived->getId())
            ? throw new InvalidResponseException(
                "Invalid Id '" . print_r($this->shareReceived->getId(), true) . "'",
            )
            : $this->shareReceived->getId();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getName(): string
    {
        return empty($this->shareReceived->getName())
            ? throw new InvalidResponseException(
                "Invalid resource name '" . print_r($this->shareReceived->getName(), true) . "'",
            )
            : $this->shareReceived->getName();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getEtag(): string
    {
        return empty($this->shareReceived->getETag())
            ? throw new InvalidResponseException(
                "Invalid Etag '" . print_r($this->shareReceived->getETag(), true) . "'",
            )
        : $this->shareReceived->getETag();
    }

    /**
     * @return \DateTimeImmutable
     * @throws InvalidResponseException
     */
    public function getLastModifiedDateTime(): \DateTimeImmutable
    {
        $time = $this->shareReceived->getLastModifiedDateTime();
        if (empty($time)) {
            throw new InvalidResponseException(
                "Invalid last modified DateTime'" . print_r($time, true) . "'",
            );
        }
        return \DateTimeImmutable::createFromMutable($time);
    }

    /**
     * @throws InvalidResponseException
     */
    public function getRemoteItem(): RemoteItem
    {
        return empty($this->shareReceived->getRemoteItem())
            ? throw new InvalidResponseException(
                "Invalid remote item '" . print_r($this->shareReceived->getParentReference(), true) . "'",
            ) : $this->shareReceived->getRemoteItem();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getRemoteItemId(): string
    {
        $remoteItem = $this->getRemoteItem();
        return empty($remoteItem->getId())
            ? throw new InvalidResponseException(
                "Invalid remote item id '" . print_r($this->shareReceived->getRemoteItem(), true) . "'",
            )
            : $remoteItem->getId();
    }

    /**
     * @throws InvalidResponseException
     */
    private function getCreatedByUser(): Identity
    {
        return empty($this->shareReceived->getCreatedBy())
        || empty($this->shareReceived->getCreatedBy()->getUser()) ?
            throw new InvalidResponseException(
                "Invalid share createdBy information '" . print_r($this->shareReceived->getCreatedBy(), true) . "'",
            ) : $this->shareReceived->getCreatedBy()->getUser();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getCreatedByDisplayName(): string
    {
        $createdByUser = $this->getCreatedByUser();
        return empty($createdByUser->getDisplayName())
            ? throw new InvalidResponseException(
                "Invalid share owner name '" . print_r($createdByUser, true) . "'",
            )
            : $createdByUser->getDisplayName();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getCreatedByUserId(): string
    {
        $createdByUser = $this->getCreatedByUser();
        return empty($createdByUser->getId()) ? throw new InvalidResponseException(
            "Invalid share owner id '" . print_r($createdByUser->getId(), true) . "'",
        ) : $createdByUser->getId();
    }

    /**
     * @throws InvalidResponseException
     */
    public function isUiHidden(): bool
    {
        $uiHidden = $this->shareReceived->getAtUiHidden();
        if (is_bool($uiHidden)) {
            return $uiHidden;
        }
        throw new InvalidResponseException('Invalid "@ui.hidden" parameter in permission');
    }

    /**
     * @throws InvalidResponseException
     */
    public function isClientSynchronized(): bool
    {
        $clientSyncronize = $this->shareReceived->getAtClientSynchronize();
        if (is_bool($clientSyncronize)) {
            return $clientSyncronize;
        }
        throw new InvalidResponseException('Invalid "@client.synchronize" parameter in permission');

    }
}
