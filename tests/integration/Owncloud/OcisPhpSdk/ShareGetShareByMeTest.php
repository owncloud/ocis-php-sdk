<?php

namespace integration\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\ShareLink;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

class ShareGetShareByMeTest extends OcisPhpSdkTestCase
{
    private Ocis $ocis;
    private User $einstein;
    private Drive $personalDrive;
    private SharingRole $editorRole;
    private OcisResource $sharedResource;
    public function setUp(): void
    {
        parent::setUp();
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];
        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->personalDrive->createFolder('newFolder');
        $this->createdResources[$this->personalDrive->getId()][] = '/newFolder';
        $resources = $this->personalDrive->getResources();
        $sharedResource = null;
        foreach ($resources as $resource) {
            if ($resource->getName() === 'newFolder') {
                $sharedResource = $resource;
            }
        }
        if ($sharedResource === null) {
            throw new \Error("resource not found ");
        }
        $this->sharedResource = $sharedResource;

        $editorRole = null;
        foreach ($this->sharedResource->getRoles() as $role) {
            if ($role->getDisplayName() === 'Editor') {
                $editorRole = $role;
            }
        }
        if ($editorRole === null) {
            throw new \Error("Editor role not found");
        }
        $this->editorRole = $editorRole;
    }

    public function testGetShareByMe(): void
    {
        $this->sharedResource->invite($this->einstein, $this->editorRole);
        $myShare = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(ShareCreated::class, $myShare[0]);
        $this->assertEquals('Albert Einstein', $myShare[0]->getReceiver()->getDisplayName());
        $this->assertSame($this->sharedResource->getId(), $myShare[0]->getResourceId());
        $this->assertSame($this->personalDrive->getId(), $myShare[0]->getDriveId());
    }

    public function testGetShareLinkByMe(): void
    {
        $this->sharedResource->createSharingLink(
            SharingLinkType::VIEW,
            new \DateTimeImmutable('2024-12-31 01:02:03.456789'),
            self::VALID_LINK_PASSWORD,
            ''
        );
        $myShare = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(ShareLink::class, $myShare[0]);
        $this->assertSame($this->sharedResource->getId(), $myShare[0]->getResourceId());
        $this->assertSame($this->personalDrive->getId(), $myShare[0]->getDriveId());
    }

    public function testGetShareAndShareLinkByMe(): void
    {
        $this->sharedResource->invite($this->einstein, $this->editorRole);
        $this->sharedResource->createSharingLink(
            SharingLinkType::VIEW,
            new \DateTimeImmutable('2024-12-31 01:02:03.456789'),
            self::VALID_LINK_PASSWORD,
            ''
        );
        $myShare = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(ShareCreated::class, $myShare[0]);
        $this->assertInstanceOf(ShareLink::class, $myShare[1]);
        $this->assertEquals($this->sharedResource->getId(), $myShare[0]->getResourceId());
        $this->assertEquals($this->sharedResource->getId(), $myShare[1]->getResourceId());
        $this->assertSame($this->personalDrive->getId(), $myShare[0]->getDriveId());
        $this->assertSame($this->personalDrive->getId(), $myShare[1]->getDriveId());
    }
}
