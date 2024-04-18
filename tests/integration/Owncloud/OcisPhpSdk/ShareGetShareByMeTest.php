<?php

namespace integration\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
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
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->personalDrive = $this->getPersonalDrive($this->ocis);
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
        $this->assertInstanceOf(
            ShareCreated::class,
            $myShare[0],
            "Expected class " . ShareCreated::class
                . " but got " . get_class($myShare[0])
        );
        $this->assertSame(
            'Albert Einstein',
            $myShare[0]->getReceiver()->getDisplayName(),
            "Expected receiver display name to be 'Albert Einstein' but found " . $myShare[0]->getReceiver()->getDisplayName()
        );
        $this->assertSame(
            $this->sharedResource->getId(),
            $myShare[0]->getResourceId(),
            "ResourceId doesn't match with Shared ResourceId"
        );
        $this->assertSame(
            $this->personalDrive->getId(),
            $myShare[0]->getDriveId(),
            "Drive Id doesn't match"
        );
    }

    public function testGetShareLinkByMe(): void
    {
        $this->sharedResource->createSharingLink(
            SharingLinkType::VIEW,
            new \DateTimeImmutable(date('Y', strtotime('+1 year'))),
            self::VALID_LINK_PASSWORD,
            ''
        );
        $myShare = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(
            ShareLink::class,
            $myShare[0],
            "Expected class " . ShareLink::class
            . " but got " . get_class($myShare[0])
        );
        $this->assertSame(
            $this->sharedResource->getId(),
            $myShare[0]->getResourceId(),
            "ResourceId doesn't match with Shared ResourceId"
        );
        $this->assertSame(
            $this->personalDrive->getId(),
            $myShare[0]->getDriveId(),
            "DriveId doesn't match"
        );
    }

    public function testGetShareAndShareLinkByMe(): void
    {
        $this->sharedResource->invite($this->einstein, $this->editorRole);
        $this->sharedResource->createSharingLink(
            SharingLinkType::VIEW,
            new \DateTimeImmutable(date('Y', strtotime('+1 year'))),
            self::VALID_LINK_PASSWORD,
            ''
        );
        $myShares = $this->ocis->getSharedByMe();
        $this->assertInstanceOf(
            ShareCreated::class,
            $myShares[0],
            "Expected class " . ShareCreated::class
            . " but got " . get_class($myShares[0])
        );
        $this->assertInstanceOf(
            ShareLink::class,
            $myShares[1],
            "Expected class " . ShareLink::class
            . " but got " . get_class($myShares[1])
        );
        foreach ($myShares as $myshare) {
            $this->assertSame(
                $this->sharedResource->getId(),
                $myshare->getResourceId(),
                "ResourceId doesn't match with shared resourceId"
            );
            $this->assertSame(
                $this->personalDrive->getId(),
                $myshare->getDriveId(),
                "DriveId doesn't match"
            );
        }
    }
}
