<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ConflictException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\ShareReceived; // @phan-suppress-current-line PhanUnreferencedUseNormal it's used in a comment
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ResourceInviteTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private User $marie;
    private SharingRole $viewerRole;
    private SharingRole $editorRole;
    private OcisResource $fileToShare;
    private OcisResource $folderToShare;
    private Drive $personalDrive;
    private Ocis $ocis;
    private Ocis $einsteinOcis;
    private Ocis $marieOcis;
    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->marieOcis = $this->initUser('marie', 'radioactivity');
        $this->ocis = $this->getOcis('admin', 'admin');
        $this->personalDrive = $this->getPersonalDrive($this->ocis);

        $this->personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$this->personalDrive->getId()][] = 'to-share-test.txt';
        $this->personalDrive->createFolder('folder-to-share');
        $this->createdResources[$this->personalDrive->getId()][] = 'folder-to-share';
        $resources = $this->personalDrive->getResources();
        foreach ($resources as $resource) {
            if ($resource->getName() === 'to-share-test.txt') {
                $this->fileToShare = $resource;
            }
            if ($resource->getName() === 'folder-to-share') {
                $this->folderToShare = $resource;
            }
        }

        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->marie = $this->ocis->getUsers('marie')[0];

        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('Viewer')) {
                $this->viewerRole = $role;
            }
            if ($role->getId() === self::getPermissionsRoleIdByName('File Editor')) {
                $this->editorRole = $role;
            }
        }
        $this->assertNotNull($this->viewerRole, 'Viewer role is empty');
        $this->assertNotNull($this->editorRole, 'Editor role is empty');
    }

    public function testInviteUser(): void
    {
        $share = $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->assertNull($share->getExpiration(), "Expiration date for sharing resource wasn't found to be null");
        $this->assertSame(
            $this->fileToShare->getId(),
            $share->getResourceId(),
            "ResourceId doesn't match with Shared ResourceId",
        );
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertCount(
            1,
            $receivedShares,
            "Failed to share resource " . $this->fileToShare->getName() . " with Einstein",
        );
        $this->assertSame(
            $this->fileToShare->getName(),
            $receivedShares[0]->getName(),
            "Expected resource name to be " . $this->fileToShare->getName() . " but found " . $receivedShares[0]->getName(),
        );
    }

    public function testInviteAnotherUser(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $share = $this->fileToShare->invite($this->marie, $this->viewerRole);
        $this->assertSame(
            $this->fileToShare->getId(),
            $share->getResourceId(),
            "ResourceId doesn't match with Shared ResourceId",
        );
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->marieOcis);
        $this->assertCount(
            1,
            $receivedShares,
            "Failed to share resource " . $this->fileToShare->getName() . " with Marie",
        );
        $this->assertSame(
            "to-share-test.txt",
            $receivedShares[0]->getName(),
            "Expected resource name to be " . $this->fileToShare->getName()
            . " but found " . $receivedShares[0]->getName(),
        );
    }

    public function testInviteGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group',
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $share = $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->assertSame(
            $this->fileToShare->getId(),
            $share->getResourceId(),
            "ResourceId doesn't match with Shared ResourceId",
        );
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertCount(
            1,
            $receivedShares,
            "Failed to share resource " . $this->fileToShare->getName() . " with group ",
        );
        $this->assertSame(
            $this->fileToShare->getName(),
            $receivedShares[0]->getName(),
            "Expected resource name to be " . $this->fileToShare->getName()
            . " but found " . $receivedShares[0]->getName(),
        );
    }

    public function testInviteSameUserAgain(): void
    {
        $this->expectException(ConflictException::class);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
    }

    public function testInviteSameUserAgainWithDifferentRole(): void
    {
        $this->expectException(ConflictException::class);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->einstein, $this->editorRole);
    }

    public function testInviteWithExpiry(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $share = $this->fileToShare->invite($this->einstein, $this->viewerRole, $tomorrow);
        $shareResourceExpirationDateTime = $share->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $shareResourceExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($shareResourceExpirationDateTime, true),
        );
        $this->assertSame(
            $tomorrow->getTimestamp(),
            $shareResourceExpirationDateTime->getTimestamp(),
            "Expected timestamp of created share of resource to be " . $tomorrow->getTimestamp() . " but found "
            . $shareResourceExpirationDateTime->getTimestamp(),
        );
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(
            1,
            $createdShares,
            $this->fileToShare->getName() . " couldn't be shared",
        );
        $createdSharesExpirationDateTime =  $createdShares[0]->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdSharesExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdSharesExpirationDateTime, true),
        );
        $this->assertSame(
            $tomorrow->getTimestamp(),
            $createdSharesExpirationDateTime->getTimestamp(),
            "Expected timestamp of shared resource to be " . $tomorrow->getTimestamp() . " but found "
            . $createdSharesExpirationDateTime->getTimestamp(),
        );
    }

    public function testInviteWithPastExpiry(): void
    {
        $this->expectException(BadRequestException::class);
        $yesterday = new \DateTimeImmutable('yesterday');
        $this->fileToShare->invite($this->einstein, $this->viewerRole, $yesterday);
    }

    public function testInviteWithExpiryTimezone(): void
    {
        $expiry = new \DateTimeImmutable('2060-01-01 12:00:00', new \DateTimeZone('Europe/Kyiv'));
        $share = $this->fileToShare->invite($this->marie, $this->viewerRole, $expiry);
        $shareResourceExpirationDateTime = $share->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $shareResourceExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($shareResourceExpirationDateTime, true),
        );
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame(
            "Thu, 01 Jan 2060 10:00:00 +0000",
            $shareResourceExpirationDateTime->format('r'),
            "Expected expiration datetime of shared resource doesn't match",
        );
        $this->assertSame(
            "Z",
            $shareResourceExpirationDateTime->getTimezone()->getName(),
            "Expected timezone to be Z but found " . $shareResourceExpirationDateTime->getTimezone()->getName(),
        );
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(
            1,
            $createdShares,
            "Expected share created to be 1 but found " . count($createdShares),
        );
        $createdSharesExpirationDateTime = $createdShares[0]->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $createdSharesExpirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($createdSharesExpirationDateTime, true),
        );
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame(
            "Thu, 01 Jan 2060 10:00:00 +0000",
            $createdSharesExpirationDateTime->format('r'),
            "Expected expiration datetime of created share of resource doesn't match ",
        );
        $this->assertSame(
            "Z",
            $createdSharesExpirationDateTime->getTimezone()->getName(),
            "Expected timezone to be Z but found " . $createdSharesExpirationDateTime->getTimezone()->getName(),
        );
    }

    public function testGetReceiversOfShareCreatedByInvite(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group',
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = [];
        $shares[] = $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $shares[] = $this->fileToShare->invite($this->marie, $this->viewerRole);
        $shares[] = $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->assertSame(
            "Albert Einstein",
            $shares[0]->getReceiver()->getDisplayName(),
            "Expected receiver display name to be Albert Einstein but found " . $shares[0]->getReceiver()->getDisplayName(),
        );
        $this->assertSame(
            "Marie Curie",
            $shares[1]->getReceiver()->getDisplayName(),
            "Expected receiver display name to be Marie Curie but found " . $shares[1]->getReceiver()->getDisplayName(),
        );
        $this->assertSame(
            "philosophyhaters",
            $shares[2]->getReceiver()->getDisplayName(),
            "Expected receiver display name to be philosophyhaters but found " . $shares[2]->getReceiver()->getDisplayName(),
        );
    }

    public function testGetReceiversOfShareReturnedBySharedByMe(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group',
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->marie, $this->viewerRole);
        $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->folderToShare->invite($this->einstein, $this->viewerRole);
        $this->folderToShare->invite($this->marie, $this->viewerRole);
        $this->folderToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        $this->assertCount(
            6,
            $shares,
            "Expected count of shared resources to be 6 but found " . count($shares),
        );
        for($i = 0; $i < 6; $i++) {
            $this->assertInstanceOf(
                ShareCreated::class,
                $shares[$i],
                "Expected class to be 'ShareCreated' but found "
                . get_class($shares[$i]),
            );
            $this->assertSame(
                $this->personalDrive->getId(),
                $shares[$i]->getDriveId(),
                "Driveid doesn't match",
            );
            $this->assertThat(
                $shares[$i]->getResourceId(),
                $this->logicalOr(
                    $this->equalTo($this->folderToShare->getId()),
                    $this->equalTo($this->fileToShare->getId()),
                ),
                "Resource Id doesn't match",
            );
            $this->assertThat(
                $shares[$i]->getReceiver()->getDisplayName(),
                $this->logicalOr(
                    $this->equalTo("philosophyhaters"),
                    $this->equalTo("Marie Curie"),
                    $this->equalTo("Albert Einstein"),
                ),
                "Expected display name of Receiver be either philosophyhaters,Marie Curie or Albert Einstein
                but found " . $shares[$i]->getReceiver()->getDisplayName(),
            );

        }
    }
}
