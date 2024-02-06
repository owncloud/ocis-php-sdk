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

class ShareGetSharedWithMeTest extends OcisPhpSdkTestCase
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
            if ($role->getId() === self::getPermissionsRoleIdByName('File Editor')) {
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
        $this->assertInstanceOf(
            ShareReceived::class,
            $receivedShare,
            "Expected class to be 'ShareReceived' but found "
            . get_class($receivedShare)
        );
        $this->assertGreaterThanOrEqual(
            1,
            strlen($receivedShare->getRemoteItemId()),
            "Expected the length of remote item id to be greater than 1"
        );
        $this->assertNotNull($receivedShare->getId(), "Expected received share id to not be null");
        $this->assertGreaterThanOrEqual(
            1,
            strlen((string)$receivedShare->getId()),
            " The length of received share id to be greater than 1 "
        );
        $this->assertEquals(
            $this->fileToShare->getName(),
            $receivedShare->getName(),
            "Expected shared file to be " . $this->fileToShare->getName() . " but found " . $receivedShare->getName()
        );
        $this->assertSame(
            $this->fileToShare->getEtag(),
            $receivedShare->getEtag(),
            "Resource Etag of shared resource doesn't match"
        );
        $this->assertSame(
            $this->fileToShare->getId(),
            $receivedShare->getRemoteItemId(),
            "The file-id of the remote item in the receive share is different to the id of the shared file"
        );

        $this->assertFalse($receivedShare->isUiHidden(), "Expected receive share to be hidden");
        $this->assertTrue(
            $receivedShare->isClientSynchronized(),
            "Expected received share to be client synchronized, but found not synced"
        );
        $this->assertEqualsWithDelta(
            time(),
            $receivedShare->getLastModifiedDateTime()->getTimestamp(),
            120,
            "Expected Shared resource was last modified within 120 seconds of the current time"
        );
        $this->assertStringContainsString(
            'Admin',
            $receivedShare->getCreatedByDisplayName(),
            "Expected owner name to be 'Admin' but found " . $receivedShare->getCreatedByDisplayName()
        );
        $this->assertGreaterThanOrEqual(
            1,
            strlen($receivedShare->getCreatedByUserId()),
            "Expected the length of ownerId of receive share to be greater than 1"
        );
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
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        foreach($receivedShares as $receivedShare) {
            $this->assertInstanceOf(
                ShareReceived::class,
                $receivedShare,
                "Expected class to be 'ShareReceived' but found "
                . get_class($receivedShare)
            );
        }
        $this->assertCount(
            2,
            $receivedShares,
            "Expected two shares but found " . count($receivedShares)
        );
        for($i = 0; $i < 2; $i++) {
            $this->assertThat(
                $receivedShares[$i]->getName(),
                $this->logicalOr(
                    $this->equalTo($this->fileToShare->getName()),
                    $this->equalTo($this->folderToShare->getName())
                ),
                "Expected shared resource name to be " . $this->fileToShare->getName() . " or " . $this->folderToShare->getName().
                " but found " . $receivedShares[$i]->getName()
            );
            $this->assertThat(
                $receivedShares[$i]->getRemoteItemId(),
                $this->logicalOr(
                    $this->equalTo($this->fileToShare->getId()),
                    $this->equalTo($this->folderToShare->getId())
                ),
                "Expected shared resource Id to be " . $this->fileToShare->getId() . " or " . $this->folderToShare->getId()
                . " but found " . $receivedShares[$i]->getRemoteItemId()
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
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        foreach($receivedShares as $receivedShare) {
            $this->assertInstanceOf(
                ShareReceived::class,
                $receivedShare,
                "Expected class to be 'ShareReceived' but found "
                . get_class($receivedShare)
            );
            $permissions = $receivedShare->getRemoteItem()->getPermissions() ?? [];
            $this->assertCount(
                2,
                $permissions,
                "Expected two shares but found " . count($receivedShares)
            );
        }

        $this->assertSame(
            $receivedShares[0]->getName(),
            $this->fileToShare->getName(),
            "Expected resource name to be " .  $receivedShares[0]->getName()
            . " but found " . $this->fileToShare->getName()
        );
        $this->assertSame(
            $receivedShares[0]->getRemoteItemId(),
            $this->fileToShare->getId(),
            "Expected resource id to be " .  $receivedShares[0]->getRemoteItemId()
            . " but found " . $this->fileToShare->getId()
        );
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
        $this->assertCount(
            2,
            $receivedShares,
            "Expected two receive shares but found " . count($receivedShares)
        );
        // the resources in the share-jail are merged if received by different ways
        $this->assertCount(
            2,
            $resourcesInShareJail,
            "Expected two receive shares but found " . count($resourcesInShareJail)
        );
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
                    $share->getName() === $resource->getName()
                ) {
                    $foundMatchingShare = true;
                }
            }
            $this->assertTrue($foundMatchingShare, "No matching share found");
        }
    }
}
