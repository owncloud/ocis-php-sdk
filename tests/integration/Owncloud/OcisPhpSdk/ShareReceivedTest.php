<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\ShareReceived; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ShareReceivedTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private SharingRole $viewerRole;
    private OcisResource $fileToShare;
    private Drive $personalDrive;
    private Ocis $ocis;
    private Ocis $einsteinOcis;
    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->personalDrive = $this->getPersonalDrive($this->ocis);

        $this->personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$this->personalDrive->getId()][] = 'to-share-test.txt';
        $this->fileToShare = $this->personalDrive->getResources()[0];
        $this->einstein = $this->ocis->getUsers('einstein')[0];

        /**
         * @var SharingRole $role
         */
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getDisplayName() === 'Viewer') {
                $this->viewerRole = $role;
            }
        }
    }

    public function testGetAttributesOfReceivedShare(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        /**
         * @var ShareReceived $receivedShare
         */
        $receivedShare = $this->einsteinOcis->getSharedWithMe()[0];
        $this->assertInstanceOf(ShareReceived::class, $receivedShare);
        $this->assertMatchesRegularExpression('/' . $this->getUUIDv4Regex() . '/', $receivedShare->getId());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getName());
        // multiple issues with id in getSharedWithMe, see https://github.com/owncloud/ocis/issues/8000
        // $this->assertSame($this->personalDrive->getId(), $receivedShare->getParentDriveId());
        // shareWithMe does not return a drive type for parentReference, see https://github.com/owncloud/ocis/issues/8029
        // $this->assertSame($this->personalDrive->getType(), $receivedShare->getParentDriveType());
        // etags returned by sharedWithMe is not quoted, see https://github.com/owncloud/ocis/issues/8045
        // $this->assertSame($this->fileToShare->getEtag(), $receivedShare->getEtag());
        $this->assertSame(
            $this->fileToShare->getId(),
            $receivedShare->getRemoteItemId(),
            "Expected Shared Resource Id doesn't match with Resource Id"
        );
        $this->assertSame(
            $this->fileToShare->getName(),
            $receivedShare->getRemoteItemName(),
            "Expected Shared Resource Name be " . $this->fileToShare->getName()
            ." but found " . $receivedShare->getRemoteItemName()
        );
        $this->assertSame(
            $this->fileToShare->getSize(),
            $receivedShare->getRemoteItemSize(),
            "Expected Shared Resource File Size doesn't match with Resource Size"
        );
        $this->assertEqualsWithDelta(
            time(),
            $receivedShare->getRemoteItemSharedDateTime()->getTimestamp(),
            120,
            "Expected Shared resource wasn't modified within 120 seconds of the current time "
        );
        $this->assertStringContainsString(
            'Admin',
            $receivedShare->getOwnerName(),
            "Expected owner name to be 'Admin' but found " . $receivedShare->getOwnerName()
        );
        $this->assertMatchesRegularExpression(
            '/' . $this->getUUIDv4Regex() . '/',
            $receivedShare->getOwnerId(),
            "OwnerId doesn't match the expected format"
        );
    }
}
