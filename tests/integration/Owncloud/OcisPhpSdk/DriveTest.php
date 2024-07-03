<?php

namespace integration\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\EndPointNotImplementedException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\SharingRole;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

class DriveTest extends OcisPhpSdkTestCase
{
    private Drive $drive;
    private Ocis $ocis;
    public function setUp(): void
    {
        parent::setUp();
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->drive = $this->ocis->createDrive('test drive');
        $this->createdDrives[] = $this->drive->getId();
    }

    public function testDisableDrive(): void
    {
        $this->assertFalse($this->drive->isDisabled(), $this->drive->getName() . " drive is expected to be enabled initially");
        $this->drive->disable();
        $this->assertTrue($this->drive->isDisabled(), "Failed to disable the drive " . $this->drive->getName());
    }

    public function testEnableDrive(): void
    {
        $this->drive->disable();
        $this->drive->enable();
        $this->assertFalse($this->drive->isDisabled(), "Failed to enable the drive " . $this->drive->getName());
    }

    public function testEnableNotExistingDrive(): void
    {
        $this->drive->disable();
        $this->drive->delete();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('itemNotFound - drive not found');
        $this->drive->enable();
    }

    public function testDeleteDrive(): void
    {
        $this->drive->disable();
        $this->drive->delete();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('itemNotFound - no drive returned from storage');
        $this->ocis->getDriveById($this->drive->getId());
    }

    public function testDeleteNotExistingDrive(): void
    {
        $this->drive->disable();
        $this->drive->delete();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('itemNotFound - drive not found');
        $this->drive->delete();
    }

    public function testDeleteEnabledDrive(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('invalidRequest - error: bad request: can\'t purge enabled space');
        $this->drive->delete();
    }

    public function testGetDriveRole(): void
    {
        //ocis stable doesn't support root endpoint
        if (getenv('OCIS_VERSION') === "stable") {
            $this->expectException(EndPointNotImplementedException::class);
            $this->expectExceptionMessage("This method is not implemented in this ocis version");
            $this->drive->getRoles();
        }
        try {
            $role = $this->drive->getRoles();
            $this->assertContainsOnlyInstancesOf(
                SharingRole::class,
                $role,
                "Array contains not only 'SharingRole' items"
            );
        } catch(EndPointNotImplementedException) {
            //test should fail if ocis version is less than 6.0.0
            $this->fail("EndPointNotImplementedException was thrown unexpectedly");
        };
    }

    public function testCreateDriveInvite(): void
    {
        // At the time of writing, "stable" is major version 5 of ocis.
        // This functionality works with major version 6.
        // When ocis major version 6 has been released as "stable" then remove this test skip.
        if (getenv('OCIS_VERSION') === "stable") {
            $this->markTestSkipped(
                'This test is skipped because root endpoint for drive share is not applicable for version 5 of OCIS.'
            );
        };
        $marieOcis = $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $managerRole = null;
        foreach ($this->drive->getRoles() as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }
        if (empty($managerRole)) {
            throw new \Error(
                "manager role not found "
            );
        }
        $this->drive->invite($marie, $managerRole);

        $receivedShareDrive = $marieOcis->getDriveById($this->drive->getId());
        $this->assertSame(
            $this->drive->getId(),
            $receivedShareDrive->getId(),
            "Expected driveId to be " . $this->drive->getId()
            . " but found " . $receivedShareDrive->getId()
        );
        $this->assertSame(
            $this->drive->getName(),
            $receivedShareDrive->getName(),
            "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedShareDrive->getName()
        );
    }
}
