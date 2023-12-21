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
        DriveItem $shareReceived
    ) {
        $this->shareReceived = $shareReceived;
    }

    /**
     *
     * @return string
     * @throws InvalidResponseException
     */
    public function getId(): string
    {
        return empty($this->shareReceived->getId())
            ? throw new InvalidResponseException(
                "Invalid Id '" . print_r($this->shareReceived->getId(), true) . "'"
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
                "Invalid resource name '" . print_r($this->shareReceived->getName(), true) . "'"
            )
            : $this->shareReceived->getName();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getEtag(): string
    {
        return empty($this->shareReceived->getETag())
            ? throw new InvalidResponseException(
                "Invalid etag '" . print_r($this->shareReceived->getETag(), true) . "'"
            )
            : $this->shareReceived->getETag();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getParentDriveId(): string
    {
        return empty($this->shareReceived->getParentReference())
        || empty($this->shareReceived->getParentReference()->getDriveId()) ?
           throw new InvalidResponseException(
               "Invalid driveId '" . print_r($this->shareReceived->getParentReference(), true) . "'"
           )
           : $this->shareReceived->getParentReference()->getDriveId();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getParentDriveType(): string
    {
        return empty($this->shareReceived->getParentReference())
        || empty($this->shareReceived->getParentReference()->getDriveType())
            ? throw new InvalidResponseException(
                "Invalid drive type '" . print_r($this->shareReceived->getParentReference(), true) . "'"
            )
            : $this->shareReceived->getParentReference()->getDriveType();
    }

    /**
     * @throws InvalidResponseException
     */
    private function getRemoteItem(): RemoteItem
    {
        return empty($this -> shareReceived -> getRemoteItem())
            ? throw new InvalidResponseException(
                "Invalid remote item'" . print_r($this -> shareReceived -> getParentReference(), true) . "'"
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
                "Invalid remote item id '" . print_r($this->shareReceived->getRemoteItem(), true) . "'"
            )
            : $remoteItem->getId();
    }

    /**
     * @return string
     * @throws InvalidResponseException
     */
    public function getRemoteItemName(): string
    {
        $remoteItem = $this->getRemoteItem();
        return empty($remoteItem->getName())
            ? throw new InvalidResponseException(
                "Invalid remote item name '" . print_r($remoteItem, true) . "'"
            )
            : $remoteItem->getName();
    }

    /**
     * @return int
     * @throws InvalidResponseException
     */
    public function getRemoteItemSize(): int
    {
        $remoteItem = $this->getRemoteItem();
        return empty($remoteItem->getSize())
            ? throw new InvalidResponseException(
                "Invalid remote item size '" . print_r($remoteItem, true) . "'"
            )
            : $remoteItem->getSize();
    }

    /**
     * @throws InvalidResponseException
     */
    private function getShared(): \OpenAPI\Client\Model\Shared
    {
        $remoteItem = $this->getRemoteItem();

        return empty($remoteItem->getShared()) ?
            throw new InvalidResponseException(
                "Invalid shared '" . print_r($remoteItem, true) . "'"
            ) : $remoteItem->getShared();
    }

    /**
     * @return \DateTimeImmutable
     * @throws InvalidResponseException
     */
    public function getRemoteItemSharedDateTime(): \DateTimeImmutable
    {
        $sharedInfo = $this->getShared();
        $time = $sharedInfo->getSharedDateTime();
        if (empty($time)) {
            throw new InvalidResponseException(
                "Invalid shared DateTime'" . print_r($sharedInfo->getSharedDateTime(), true) . "'"
            );
        }
        return \DateTimeImmutable::createFromMutable($time);
    }

    /**
     * @throws InvalidResponseException
     */
    private function getOwnerUser(): Identity
    {
        return empty($this->getShared()->getOwner())
        || empty($this->getShared()->getOwner()->getUser()) ?
            throw new InvalidResponseException(
                "Invalid owner information '" . print_r($this->getShared()->getOwner(), true) . "'"
            ) : $this->getShared()->getOwner()->getUser();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getOwnerName(): string
    {
        $ownerUser = $this->getOwnerUser();
        return empty($ownerUser->getDisplayName())
            ? throw new InvalidResponseException(
                "Invalid share owner name '" . print_r($ownerUser, true) . "'"
            )
            : $ownerUser->getDisplayName();
    }

    /**
     * @throws InvalidResponseException
     */
    public function getOwnerId(): string
    {
        $ownerUser = $this->getOwnerUser();
        return empty($ownerUser->getId()) ? throw new InvalidResponseException(
            "Invalid share owner id '" . print_r($ownerUser->getId(), true) . "'"
        ) : $ownerUser->getId();
    }
}
