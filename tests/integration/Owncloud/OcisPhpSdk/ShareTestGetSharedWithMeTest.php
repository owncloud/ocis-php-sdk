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

    public function testGetShareWithMe(): void
    {
        $this->folderToShare->invite($this->einstein, $this->editorRole);
        $receivedShare = $this->einsteinOcis->getSharedWithMe();
        $this->assertInstanceOf(ShareReceived::class, $receivedShare[0]);
        $this->assertEquals($this->folderToShare->getName(), $receivedShare[0]->getName());
        $this->assertSame($this->folderToShare->getId(), $receivedShare[0]->getRemoteItemId());
        //The step will only work after this bug is solved https://github.com/owncloud/ocis/issues/8000
        //$this->assertSame($this->personalDrive->getId(), $receivedShare[0]->getDriveId());
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
        $receivedShare = $this->einsteinOcis->getSharedWithMe();
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
