<?php

namespace integration\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Model\Permission;
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
    private string $driveName = 'test drive';
    public function setUp(): void
    {
        parent::setUp();
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->drive = $this->ocis->createDrive($this->driveName);
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
        $role = $this->drive->getRoles();
        $this->assertContainsOnlyInstancesOf(
            SharingRole::class,
            $role,
            "Array contains not only 'SharingRole' items",
        );
    }

    public function testSetValidName(): void
    {
        $name = 'test name';
        $this->assertEquals($this->drive->getName(), $this->driveName);
        $this->drive->setName($name);
        $this->assertEquals($this->drive->getName(), $name, "Failed to set name $name");
    }

    public function testSetInvalidName(): void
    {
        if (getenv('OCIS_VERSION') === "stable") {
            $this->markTestSkipped('https://github.com/owncloud/ocis/issues/11887');
        }
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('spacename must not be empty');

        $this->drive->setName('');
    }
    /**
     * @dataProvider descriptionStrings
     */
    public function testSetDescription(string $description): void
    {
        $this->drive->setDescription($description);
        $this->assertEquals($this->drive->getDescription(), $description, "Failed to set description $description");
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function descriptionStrings(): array
    {
        return [
            [''],
            ['test string'],
          ];
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

        $shareRoles = $this->drive->getRoles();
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }

        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $driveInvitation = $this->drive->invite($marie, $managerRole);

        $this->assertInstanceOf(
            Permission::class,
            $driveInvitation,
            "Expected class to be 'Permission' but found "
            . get_class($driveInvitation),
        );
        $this->assertNull($driveInvitation->getExpirationDateTime(), "Expiration date for sharing drive wasn't found to be null");
        $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());
        $this->assertSame(
            $this->drive->getId(),
            $receivedInvitationDrive->getId(),
            "Expected driveId to be " . $this->drive->getId()
            . " but found " . $receivedInvitationDrive->getId(),
        );
        $this->assertSame(
            $this->drive->getName(),
            $receivedInvitationDrive->getName(),
            "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName(),
        );
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

        foreach ($this->drive->getRoles() as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }

        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $this->drive->invite($marie, $managerRole);
        $permissions = $this->drive->getPermissions();
        $permissionId = null;
        foreach ($permissions as $permission) {
            $grantedToV2 = $permission->getGrantedToV2();
            if ($grantedToV2 && $grantedToV2->getUser() && $grantedToV2->getUser()->getDisplayName() === 'Marie Curie') {
                $permissionId = $permission->getId();
            }
        }

        $this->assertIsString($permissionId, "Permission not found of user Marie Curie");

        $isDriveShareDeleted = $this->drive->deletePermission($permissionId);
        $this->assertTrue($isDriveShareDeleted);
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

        $driveRoles = $this->drive->getRoles();
        foreach ($driveRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");
        $this->drive->invite($marie, $managerRole);
        $nonManagerRoleFound = false;
        foreach ($driveRoles as $role) {
            if ($role->getId() !== self::getPermissionsRoleIdByName('Manager')) {
                $nonManagerRoleFound = true;
                $permissions = $this->drive->getPermissions();
                $permissionId = null;
                foreach ($permissions as $permission) {
                    $grantedToV2 = $permission->getGrantedToV2();
                    if ($grantedToV2 && $grantedToV2->getUser() && $grantedToV2->getUser()->getDisplayName() === 'Marie Curie') {
                        $permissionId = $permission->getId();
                    }
                }

                $this->assertIsString($permissionId, "Permission not found of user Marie Curie");
                $isRoleSet = $this->drive->setPermissionRole($permissionId, $role);

                $this->assertTrue($isRoleSet, "Failed to set role");
            }
        }
        $this->assertTrue($nonManagerRoleFound, "Only the Manager role exists. setPermissionRole was never called");
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

        foreach ($this->drive->getRoles() as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");
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

        $this->assertIsString($permissionId, "Permission not found of user Marie Curie");

        $isExpirationDateUpdated = $this->drive->setPermissionExpiration($permissionId, $oneYearTime);
        $this->assertTrue($isExpirationDateUpdated, "Expected expiration date to be updated");
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
            'philosophy haters group',
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($marie);

        $managerRole = null;

        $shareRoles = $this->drive->getRoles();
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }

        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $this->drive->invite($philosophyHatersGroup, $managerRole);
        $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());

        $this->assertSame(
            $this->drive->getId(),
            $receivedInvitationDrive->getId(),
            "Expected driveId to be " . $this->drive->getId()
            . " but found " . $receivedInvitationDrive->getId(),
        );
        $this->assertSame(
            $this->drive->getName(),
            $receivedInvitationDrive->getName(),
            "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName(),
        );
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

        $shareRoles = $this->drive->getRoles();
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }

        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $this->drive->invite($marie, $managerRole);
        $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());

        $this->assertSame(
            $this->drive->getName(),
            $receivedInvitationDrive->getName(),
            "Expected shared drive name to be " . $this->drive->getName() . " but found " . $receivedInvitationDrive->getName(),
        );
        $this->assertSame(
            'myfolder',
            $receivedInvitationDrive->getResources()[0]->getName(),
            "Expected resource name to be myfolder"
            . " but found " . $receivedInvitationDrive->getResources()[0]->getName(),
        );
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

        $shareRoles = $this->drive->getRoles();
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }

        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $this->drive->invite($katherine, $managerRole);

        $katherineDrives = $katherineOcis->getMyDrives();
        $projectDriveFound = false;

        foreach ($katherineDrives as $drive) {
            if ($drive->getType() === DriveType::PROJECT) {
                $projectDriveFound = true;
                $this->assertThat(
                    $drive->getName(),
                    $this->logicalOr(
                        $this->equalTo('katherine Project Drive'),
                        $this->equalTo('test drive'),
                    ),
                    "Expected drivename to be either:"
                . "'katherine Project Drive' or 'test drive' but found "
                . $drive->getName(),
                );
            }
        }

        $this->assertTrue($projectDriveFound, "No project drive was found for Katherine");
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
    public function testReceiverInviteOtherUserToDriveShare(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $katherineOcis = $this->getOcis('katherine', 'gemini');
        $katherine = $this->ocis->getUsers('katherine')[0];
        $marie = $this->ocis->getUsers('marie')[0];
        $managerRole = null;
        $shareRoles = $this->drive->getRoles();
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $this->drive->invite($marie, $managerRole);
        $marieReceivedProjectDrive = $marieOcis->getDriveById($this->drive->getId());
        $this->assertSame(
            $this->drive->getId(),
            $marieReceivedProjectDrive->getId(),
            "Expected driveId to be " . $this->drive->getId()
            . " but found " . $marieReceivedProjectDrive->getId(),
        );
        $marieReceivedProjectDrive->invite($katherine, $managerRole);
        $katherineReceivedProjectDrive = $katherineOcis->getDriveById($this->drive->getId());
        $this->assertSame(
            $this->drive->getName(),
            $katherineReceivedProjectDrive->getName(),
            "Expected shared drive name to be " . $this->drive->getName() . " but found " . $katherineReceivedProjectDrive->getName(),
        );
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
    public function testReceiverInviteOtherUserToDriveShareWithNoInvitePermission(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $this->initUser('katherine', 'gemini');
        $katherine = $this->ocis->getUsers('katherine')[0];
        $marie = $this->ocis->getUsers('marie')[0];
        $shareRoles = $this->drive->getRoles();
        $viewerRole = null;
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Space Viewer')) {
                $viewerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $viewerRole, "Space viewer role not found");
        $this->drive->invite($marie, $viewerRole);
        $marieReceivedProjectDrive = $marieOcis->getDriveById($this->drive->getId());
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("accessDenied - add grant: error: permission denied:");
        $marieReceivedProjectDrive->invite($katherine, $viewerRole);
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
    public function testReceiverUpdatesDriveShareRole(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $marie = $this->ocis->getUsers('marie')[0];

        $shareRoles = $this->drive->getRoles();
        $managerRole = null;
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Manager')) {
                $managerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $managerRole, "manager role not found");

        $driveInvitation = $this->drive->invite($marie, $managerRole);
        $permissionId = $driveInvitation->getId();
        $this->assertIsString($permissionId, "Permission not found of user Marie Curie");
        $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());
        $shareRoles = $receivedInvitationDrive->getRoles();
        $spaceViewerRole = null;
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Space Viewer')) {
                $spaceViewerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $spaceViewerRole, "Space viewer role not found");
        $isRoleSet = $receivedInvitationDrive->setPermissionRole($permissionId, $spaceViewerRole);
        $this->assertTrue($isRoleSet, "Failed to set role id");
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
    public function testReceiverUpdatesDriveShareRoleNotEnoughPermission(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $marie = $this->ocis->getUsers('marie')[0];
        $shareRoles = $this->drive->getRoles();
        $spaceViewerRole = null;
        foreach ($shareRoles as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Space Viewer')) {
                $spaceViewerRole = $role;
                break;
            }
        }
        $this->assertInstanceOf(SharingRole::class, $spaceViewerRole, "Space viewer role not found");

        $driveInvitation = $this->drive->invite($marie, $spaceViewerRole);
        $permissionId = $driveInvitation->getId();
        $this->assertIsString($permissionId, "Permission not found of user Marie Curie");
        $receivedInvitationDrive = $marieOcis->getDriveById($this->drive->getId());
        $shareRoles = $receivedInvitationDrive->getRoles();
        $this->assertNotEmpty($shareRoles, "no roles found for the drive that was shared with Marie Curie");

        foreach ($shareRoles as $role) {
            $this->expectException(InternalServerErrorException::class);
            $this->expectExceptionMessage("error committing share to storage grant");
            $receivedInvitationDrive->setPermissionRole($permissionId, $role);
        }
    }
}
