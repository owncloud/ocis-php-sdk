<?php

namespace integration\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

class DriveTest extends OcisPhpSdkTestCase
{
    private Drive $drive;
    public function setUp(): void
    {
        parent::setUp();
        $ocis = $this->getOcis('admin', 'admin');
        $this->drive = $ocis->createDrive('test drive');
        $this->createdDrives[] = $this->drive->getId();
    }

    public function testDisableDrive(): void
    {
        $this->assertFalse($this->drive->isDisabled());
        $this->drive->disable();
        $this->assertTrue($this->drive->isDisabled());
    }

    public function testEnableDrive(): void
    {
        $this->drive->disable();
        $this->drive->enable();
        $this->assertFalse($this->drive->isDisabled());
    }

    public function testEnableNotExistingDrive(): void
    {
        $this->expectException(NotFoundException::class);
        $this->drive->disable();
        $this->drive->delete();
        $this->drive->enable();
        $this->assertFalse($this->drive->isDisabled());
    }
}
