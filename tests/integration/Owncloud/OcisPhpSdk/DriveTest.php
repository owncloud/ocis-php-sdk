<?php

namespace integration\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\EndPointNotImplementedException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
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
        // ocis stable doesn't support root endpoint
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
            // test should fail if ocis version is less than 6.0.0
            $this->fail("EndPointNotImplementedException was thrown unexpectedly");
        }
    }

    /**
     * @throws ForbiddenException
     * @throws InvalidResponseException
     * @throws BadRequestException
     * @throws EndPointNotImplementedException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws NotFoundException
     * @throws InternalServerErrorException
     * @throws \Exception
     */
    public function testCreateDriveInvite(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $managerRole = null;

        if (getenv('OCIS_VERSION') === "stable") {
            // ocis version < 6.0.0 doesn't support root endpoint so mocking getRoles values
            $role = [
                "id" => "312c0871-5ef7-4b3a-85b6-0e4074c64049",
                "description" => "Allows managing a space",
                "displayName" => "Manager",
                "@libre.graph.weight" => 3
            ];

            $shareRoles = [new SharingRole(new UnifiedRoleDefinition($role))];
        } else {
            $shareRoles = $this->drive->getRoles();
        }
        foreach ($shareRoles as $role) {
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
        // ocis stable doesn't support root endpoint
        if (getenv('OCIS_VERSION') === "stable") {
            $this->expectException(EndPointNotImplementedException::class);
            $this->expectExceptionMessage("This method is not implemented in this ocis version");
            $this->drive->invite($marie, $managerRole);
        }

        try {
            $driveShare = $this->drive->invite($marie, $managerRole);

            $this->assertNull($driveShare->getExpiration(), "Expiration date for sharing drive wasn't found to be null");
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
        } catch(EndPointNotImplementedException) {
            // test should fail if ocis version is less than 6.0.0
            $this->fail("EndPointNotImplementedException was thrown unexpectedly");
        }
    }
}
