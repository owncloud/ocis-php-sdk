<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
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
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];

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
        $this->assertSame($this->fileToShare->getId(), $receivedShare->getRemoteItemId());
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getRemoteItemName());
        $this->assertSame($this->fileToShare->getSize(), $receivedShare->getRemoteItemSize());
        $this->assertEqualsWithDelta(time(), $receivedShare->getRemoteItemSharedDateTime()->getTimestamp(), 120);
        $this->assertStringContainsString('Admin', $receivedShare->getOwnerName());
        $this->assertMatchesRegularExpression('/' . $this->getUUIDv4Regex() . '/', $receivedShare->getOwnerId());
    }
}
