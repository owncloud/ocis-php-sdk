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
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];
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
        $this->assertMatchesRegularExpression('/' . $this->getUUIDv4Regex() . '/', $receivedShare->getRemoteItemId());
        $this->assertMatchesRegularExpression('/' . $this->getFileIdRegex() . '/', $receivedShare->getId());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getName());
        $this->assertStringContainsString($this->personalDrive->getId(), $receivedShare->getParentDriveId());
        $this->assertSame($this->personalDrive->getType(), $receivedShare->getParentDriveType());
        $this->assertSame($this->fileToShare->getEtag(), $receivedShare->getEtag());
        $this->assertSame($this->fileToShare->getId(), $receivedShare->getRemoteItemId());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getRemoteItemName());
        $this->assertSame($this->fileToShare->getSize(), $receivedShare->getRemoteItemSize());
        $this->assertEqualsWithDelta(time(), $receivedShare->getRemoteItemSharedDateTime()->getTimestamp(), 120);
        $this->assertStringContainsString('Admin', $receivedShare->getOwnerName());
        $this->assertMatchesRegularExpression('/' . $this->getUUIDv4Regex() . '/', $receivedShare->getOwnerId());
    }

    public function testGetMultipleShareWithMe(): void
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
}
