<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\ShareReceived; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ShareTestGetSharedWithMeNotSyncedSharesTest extends OcisPhpSdkTestCase
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

    public static function setUpBeforeClass(): void
    {
        self::setOcisSetting('FRONTEND_AUTO_ACCEPT_SHARES', 'false');
    }

    public static function tearDownAfterClass(): void
    {
        self::resetOcisSettings();
    }


    public function testGetAttributesOfReceivedButNotAcceptedShare(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        /**
         * @var ShareReceived $receivedShare
         */
        $receivedShare = $this->einsteinOcis->getSharedWithMe()[0];
        $this->assertInstanceOf(ShareReceived::class, $receivedShare);
        $this->assertGreaterThanOrEqual(1, strlen($receivedShare->getRemoteItemId()));
        $this->assertSame($this->fileToShare->getName(), $receivedShare->getName());
        $this->assertFalse($receivedShare->isUiHidden());
        $this->assertFalse($receivedShare->isClientSyncronize());
        $this->assertNull($receivedShare->getId());
        $this->assertNull($receivedShare->getEtag());
        $this->assertNull($receivedShare->getParentDriveId());
        $this->assertNull($receivedShare->getParentDriveType());
        $this->assertSame(
            $this->fileToShare->getId(),
            $receivedShare->getRemoteItemId(),
            "The file-id of the remote item in the receive share is different to the id of the shared file"
        );
        $this->assertSame(
            $this->fileToShare->getName(),
            $receivedShare->getRemoteItemName(),
            "The item-name of the remote item in the receive share is different to the name of the shared file"
        );
        $this->assertSame(
            $this->fileToShare->getSize(),
            $receivedShare->getRemoteItemSize(),
            "The item-size of the remote item in the receive share is different to the size of the shared file"
        );
        $this->assertEqualsWithDelta(
            time(),
            $receivedShare->getRemoteItemSharedDateTime()->getTimestamp(),
            120,
            "Expected Shared resource was shared within 120 seconds of the current time"
        );
        $this->assertStringContainsString(
            'Admin',
            $receivedShare->getOwnerName(),
            "Expected owner name to be 'Admin' but found " . $receivedShare->getOwnerName()
        );
        $this->assertMatchesRegularExpression(
            '/' . $this->getUUIDv4Regex() . '/',
            $receivedShare->getOwnerId(),
            "OwnerId of the received share doesn't match the expected format"
        );
    }
}
