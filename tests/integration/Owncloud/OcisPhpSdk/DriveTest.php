<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

class DriveTest extends OcisPhpSdkTestCase
{
    public function testDisableDrive(): void
    {
        $ocis = $this->getOcis('admin', 'admin');
        $drive = $ocis->createDrive('test drive');
        $this->createdDrives[] = $drive->getId();
        $this->assertFalse($drive->isDisabled());
        $drive->disable();
        $this->assertTrue($drive->isDisabled());
    }
}
