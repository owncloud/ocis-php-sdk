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
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\ShareLink;
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

    public function testbesta(): void
    {
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $einstein = $einsteinOcis->getUsers('einstein')[0];
        $share = $this->drive->createLink($einstein, 'view', 'P@$$w0rd', 'user');
        $this->assertInstanceOf(ShareLink::class, $share);
    }

    public function testbestb(): void
    {
        $einsteinOcis = $this->initUser('einstein', 'relativity');
        $einstein = $einsteinOcis->getUsers('einstein')[0];
        $share = $this->drive->createLink($einstein, 'view', 'P@$$w0rd', 'user');
        $shareU = $this->drive->updateLink($share, 'edit');
        $this->assertInstanceOf(ShareLink::class, $shareU);
    }
}
