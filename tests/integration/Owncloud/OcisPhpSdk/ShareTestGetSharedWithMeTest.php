<?php

namespace integration\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\ShareReceived;
use Owncloud\OcisPhpSdk\User;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

class ShareTestGetSharedWithMeTest extends OcisPhpSdkTestCase
{
    private Ocis $ocis;
    private Ocis $einsteinOcis;
    private User $einstein;
    private Drive $personalDrive;
    private OcisResource $fileToShare;
    private OcisResource $folderToShare;
    private SharingRole $editorRole;
    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->personalDrive = $this->getPersonalDrive($this->ocis);
        $this->personalDrive->createFolder('newFolder');
        $this->personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$this->personalDrive->getId()][] = 'to-share-test.txt';
        $this->createdResources[$this->personalDrive->getId()][] = 'newFolder';
        $resources = $this->personalDrive->getResources();
        foreach ($resources as $resource) {
            if ($resource->getName() === 'to-share-test.txt') {
                $this->fileToShare = $resource;
            }
            if ($resource->getName() === 'newFolder') {
                $this->folderToShare = $resource;
            }
        }
        $editorRole = null;
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getDisplayName() === 'Editor') {
                $editorRole = $role;
            }
        }
        if ($editorRole === null) {
            throw new \Error("Editor role not found");
        }
        $this->editorRole = $editorRole;

    }

    public function testGetAttributesOfReceivedShare(): void
    {
        $this->fileToShare->invite($this->einstein, $this->editorRole);
        /**
         * @var ShareReceived $receivedShare
         */
        $receivedShare = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis)[0];
        $this->assertInstanceOf(ShareReceived::class, $receivedShare);
        $this->assertGreaterThanOrEqual(1, strlen($receivedShare->getRemoteItemId()));
        $this->assertNotNull($receivedShare->getId());
        $this->assertGreaterThanOrEqual(1, strlen((string)$receivedShare->getId()));
        $this->assertNotNull($receivedShare->getParentDriveId());
        $this->assertGreaterThanOrEqual(1, strlen((string)$receivedShare->getParentDriveId()));
        $this->assertNotNull($receivedShare->getParentDriveType());
        $this->assertSame(DriveType::VIRTUAL, $receivedShare->getParentDriveType());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getName());
        $this->assertSame($this->fileToShare->getEtag(), $receivedShare->getEtag());
        $this->assertSame($this->fileToShare->getId(), $receivedShare->getRemoteItemId());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getRemoteItemName());
        $this->assertSame($this->fileToShare->getSize(), $receivedShare->getRemoteItemSize());
        $this->assertFalse($receivedShare->isUiHidden());
        $this->assertTrue($receivedShare->isClientSyncronize());
        $this->assertEqualsWithDelta(time(), $receivedShare->getRemoteItemSharedDateTime()->getTimestamp(), 120);
        $this->assertStringContainsString('Admin', $receivedShare->getOwnerName());
        $this->assertGreaterThanOrEqual(1, strlen($receivedShare->getOwnerId()));
    }

    public function testReceiveMultipleShares(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $this->fileToShare->invite($philosophyHatersGroup, $this->editorRole);
        $this->folderToShare->invite($this->einstein, $this->editorRole);
        $receivedShare = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertInstanceOf(ShareReceived::class, $receivedShare[0]);
        $this->assertInstanceOf(ShareReceived::class, $receivedShare[1]);
        $this->assertCount(2, $receivedShare);
        for($i = 0; $i < 2; $i++) {
            $this->assertThat(
                $receivedShare[$i]->getName(),
                $this->logicalOr(
                    $this->equalTo($this->fileToShare->getName()),
                    $this->equalTo($this->folderToShare->getName())
                )
            );
            $this->assertThat(
                $receivedShare[$i]->getRemoteItemId(),
                $this->logicalOr(
                    $this->equalTo($this->fileToShare->getId()),
                    $this->equalTo($this->folderToShare->getId())
                )
            );
        }

    }

    public function testReceiveSameShareMultipleTimes(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $this->fileToShare->invite($philosophyHatersGroup, $this->editorRole);
        $this->fileToShare->invite($this->einstein, $this->editorRole);
        $receivedShare = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertInstanceOf(ShareReceived::class, $receivedShare[0]);
        $this->assertInstanceOf(ShareReceived::class, $receivedShare[1]);
        $this->assertCount(2, $receivedShare);
        for($i = 0; $i < 2; $i++) {
            $this->assertSame(
                $receivedShare[$i]->getName(),
                $this->fileToShare->getName(),
            );
            $this->assertSame(
                $receivedShare[$i]->getRemoteItemId(),
                $this->fileToShare->getId(),
            );
        }
    }

    public function testCompareSharedWithMeAndShareDrive(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $this->fileToShare->invite($philosophyHatersGroup, $this->editorRole);
        $this->fileToShare->invite($this->einstein, $this->editorRole);
        $this->folderToShare->invite($this->einstein, $this->editorRole);

        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);

        /**
         * @var Drive $shareDrive
         */
        $shareDrive = $this->einsteinOcis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::VIRTUAL
        )[0];
        $resourcesInShareJail = $shareDrive->getResources();
        $this->assertCount(3, $receivedShares);
        // the resources in the share-jail are merged if received by different ways
        $this->assertCount(2, $resourcesInShareJail);
        /**
         * @var OcisResource $resource
         */
        foreach ($resourcesInShareJail as $resource) {
            $foundMatchingShare = false;
            /**
             * @var ShareReceived $share
             */
            foreach ($receivedShares as $share) {
                if (
                    $share->getId() === $resource->getId() &&
                    $share->getName() === $resource->getName()
                ) {
                    $foundMatchingShare = true;
                }
            }
            $this->assertTrue($foundMatchingShare);
        }
    }
}
