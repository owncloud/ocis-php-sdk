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

        $viewerRoleId = self::getPermissionsRoleIdByName('Viewer');
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getId() === $viewerRoleId) {
                $this->viewerRole = $role;
                break;
            }
        }
        $this->assertNotNull($this->viewerRole, 'Viewer role is not set');
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
        $receivedShare = $this->einsteinOcis->getSharedWithMe()[0];
        $this->assertInstanceOf(
            ShareReceived::class,
            $receivedShare,
            "Expected class to be 'ShareReceived' but found "
            . get_class($receivedShare),
        );
        $this->assertGreaterThanOrEqual(
            1,
            strlen($receivedShare->getRemoteItemId()),
            "Expected the length of remote item id to be greater than 1",
        );
        $this->assertSame(
            $this->fileToShare->getName(),
            $receivedShare->getName(),
            "Expected shared file to be " . $this->fileToShare->getName() . " but found " . $receivedShare->getName(),
        );
        $this->assertFalse($receivedShare->isUiHidden(), "Expected receive share to be hidden");
        $this->assertFalse(
            $receivedShare->isClientSynchronized(),
            "Expected received share to be client synchronized, but found not synced",
        );
        $this->assertMatchesRegularExpression(
            '/^' . $this->getUUIDv4Regex() . '\$' . $this->getUUIDv4Regex() . '!' . $this->getUUIDv4Regex() . ':' . $this->getUUIDv4Regex() . ':' . $this->getUUIDv4Regex() . '$/i',
            $receivedShare->getId(),
            "Shareid doesn't match the expected format",
        );
        $this->assertSame(
            $this->fileToShare->getId(),
            $receivedShare->getRemoteItemId(),
            "The file-id of the remote item in the receive share is different to the id of the shared file",
        );
        $this->assertMatchesRegularExpression(
            "/^\"[a-f0-9:.]{1,32}\"$/",
            $receivedShare->getEtag(),
            "Resource Etag doesn't match the expected format",
        );
        $this->assertEqualsWithDelta(
            time(),
            $receivedShare->getLastModifiedDateTime()->getTimestamp(),
            120,
            "Expected Shared resource was last modified within 120 seconds of the current time",
        );
        $this->assertStringContainsString(
            'Admin',
            $receivedShare->getCreatedByDisplayName(),
            "Expected owner name to be 'Admin' but found " . $receivedShare->getCreatedByDisplayName(),
        );
        $this->assertMatchesRegularExpression(
            '/' . $this->getUUIDv4Regex() . '/',
            $receivedShare->getCreatedByUserId(),
            "OwnerId of the received share doesn't match the expected format",
        );
    }
}
