<?php

namespace integration\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveType;
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
            $driveInvitation = $this->drive->invite($marie, $managerRole);

            $this->assertInstanceOf(
                Permission::class,
                $driveInvitation,
                "Expected class to be 'Permission' but found "
                . get_class($driveInvitation)
            );
            $this->assertNull($driveInvitation->getExpirationDateTime(), "Expiration date for sharing drive wasn't found to be null");
            $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());
            $this->assertSame(
                $this->drive->getId(),
                $receivedInvitationDrive->getId(),
                "Expected driveId to be " . $this->drive->getId()
                . " but found " . $receivedInvitationDrive->getId()
            );
            $this->assertSame(
                $this->drive->getName(),
                $receivedInvitationDrive->getName(),
                "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName()
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
    public function testDeleteDriveInvite(): void
    {
        $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $managerRole = null;

        if (getenv('OCIS_VERSION') === "stable") {
            $this->markTestSkipped('Ocis version < 6.0.0 does not support creation of drive share invite so delete drive share test has been skipped');
        } else {
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
            $permissions = $this->drive->getPermissions();
            $permissionId = null;
            foreach ($permissions as $permission) {
                $grantedToV2 = $permission->getGrantedToV2();
                if ($grantedToV2 && $grantedToV2->getUser() && $grantedToV2->getUser()->getDisplayName() === 'Marie Curie') {
                    $permissionId = $permission->getId();
                }
            }

            if (empty($permissionId)) {
                throw new \Error(" Permission not found of user Marie Curie");
            }

            $isDriveShareDeleted = $this->drive->deletePermission($permissionId);
            $this->assertTrue($isDriveShareDeleted);
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
    public function testSetRole(): void
    {
        $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $managerRole = null;

        if (getenv('OCIS_VERSION') === "stable") {
            $this->markTestSkipped('Ocis version < 6.0.0 does not support creation of drive share invite so set role of shared drive test has been skipped');
        } else {
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
            foreach ($this->drive->getRoles() as $role) {
                if ($role->getId() !== self::getPermissionsRoleIdByName('Manager')) {
                    $permissions = $this->drive->getPermissions();
                    $permissionId = null;
                    foreach ($permissions as $permission) {
                        $grantedToV2 = $permission->getGrantedToV2();
                        if ($grantedToV2 && $grantedToV2->getUser() && $grantedToV2->getUser()->getDisplayName() === 'Marie Curie') {
                            $permissionId = $permission->getId();
                        }
                    }

                    if (empty($permissionId)) {
                        throw new \Error(" Permission not found of user Marie Curie");
                    }
                    $isRoleSet = $this->drive->setPermissionRole($permissionId, $role);

                    $this->assertTrue($isRoleSet, "Failed to set role");
                }
            }
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
    public function testSetExpirationDate(): void
    {
        $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $managerRole = null;

        if (getenv('OCIS_VERSION') === "stable") {
            $this->markTestSkipped('Ocis version < 6.0.0 does not support creation of drive share invite so set expiration date of shared drive test has been skipped');
        } else {
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
            $tomorrow = new \DateTimeImmutable('tomorrow');

            $oneYearTime = new \DateTimeImmutable(date('Y-m-d', strtotime('+1 year')));

            $this->drive->invite($marie, $managerRole, $tomorrow);
            $permissions = $this->drive->getPermissions();
            $permissionId = null;
            foreach ($permissions as $permission) {
                $grantedToV2 = $permission->getGrantedToV2();
                if ($grantedToV2 && $grantedToV2->getUser() && $grantedToV2->getUser()->getDisplayName() === 'Marie Curie') {
                    $permissionId = $permission->getId();
                }
            }

            if (empty($permissionId)) {
                throw new \Error(" Permission not found of user Marie Curie");
            }

            $isExpirationDateUpdated = $this->drive->setPermissionExpiration($permissionId, $oneYearTime);
            $this->assertTrue($isExpirationDateUpdated, "Expected expiration date to be updated");
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
    public function testDriveInviteToGroup(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];

        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($marie);

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
            $this->drive->invite($philosophyHatersGroup, $managerRole);
        }

        try {
            $this->drive->invite($philosophyHatersGroup, $managerRole);
            $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());

            $this->assertSame(
                $this->drive->getId(),
                $receivedInvitationDrive->getId(),
                "Expected driveId to be " . $this->drive->getId()
                . " but found " . $receivedInvitationDrive->getId()
            );
            $this->assertSame(
                $this->drive->getName(),
                $receivedInvitationDrive->getName(),
                "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName()
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
    public function testGetDriveShareResourceByReceiver(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');

        $marie = $this->ocis->getUsers('marie')[0];
        $this->drive->createFolder('myfolder');

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
            $this->drive->invite($marie, $managerRole);
            $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());

            $this->assertSame(
                $this->drive->getName(),
                $receivedInvitationDrive->getName(),
                "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName()
            );
            $this->assertSame(
                'myfolder',
                $receivedInvitationDrive->getResources()[0]->getName(),
                "Expected resource name to be myfolder"
                . " but found " . $receivedInvitationDrive->getResources()[0]->getName()
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
    public function testGetMyDriveForDriveShareReceiver(): void
    {
        $katherineOcis = $this->getOcis('katherine', 'gemini');
        $katherineDrive = $katherineOcis->createDrive('katherine Project Drive');
        $this->createdDrives[] = $katherineDrive->getId();
        $katherine = $this->ocis->getUsers('katherine')[0];

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
            $this->drive->invite($katherine, $managerRole);
        }

        try {
            $this->drive->invite($katherine, $managerRole);

            $katherineDrives = $katherineOcis->getMyDrives();

            foreach($katherineDrives as $drive) {
                if ($drive->getType() === DriveType::PROJECT) {
                    $this->assertThat(
                        $drive->getName(),
                        $this->logicalOr(
                            $this->equalTo('katherine Project Drive'),
                            $this->equalTo('test drive'),
                        ),
                        "Expected drivename to be either:"
                    . "'katherine Project Drive' or 'test drive' but found "
                    . $drive->getName()
                    );
                }
            }
        } catch(EndPointNotImplementedException) {
            // test should fail if ocis version is less than 6.0.0
            $this->fail("EndPointNotImplementedException was thrown unexpectedly");
        }
    }

}
