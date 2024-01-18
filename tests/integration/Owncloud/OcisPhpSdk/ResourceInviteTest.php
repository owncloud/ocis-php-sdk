<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
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
    private SharingRole $managerRole;
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
        /**
         * @var OcisResource $resource
         */
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

        /**
         * @var SharingRole $role
         */
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getDisplayName() === 'Viewer') {
                $this->viewerRole = $role;
            }
            if ($role->getDisplayName() === 'Manager') {
                $this->managerRole = $role;
            }
        }
    }

    public function testInviteUser(): void
    {
        $share = $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->assertNull($share->getExpiration());
        $this->assertSame($this->fileToShare->getId(), $share->getResourceId());
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteAnotherUser(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $share = $this->fileToShare->invite($this->marie, $this->viewerRole);
        $this->assertSame($this->fileToShare->getId(), $share->getResourceId());
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->marieOcis);
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $share = $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->assertSame($this->fileToShare->getId(), $share->getResourceId());
        $receivedShares = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis);
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteSameUserAgain(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7842');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(ForbiddenException::class);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
    }

    public function testInviteSameUserAgainWithDifferentRole(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7842');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(ForbiddenException::class);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->einstein, $this->managerRole);
    }

    public function testInviteWithExpiry(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $share = $this->fileToShare->invite($this->einstein, $this->viewerRole, $tomorrow);
        $this->assertInstanceOf(\DateTimeImmutable::class, $share->getExpiration());
        $this->assertSame($tomorrow->getTimestamp(), $share->getExpiration()->getTimestamp());
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(1, $createdShares);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiration());
        $this->assertSame($tomorrow->getTimestamp(), $createdShares[0]->getExpiration()->getTimestamp());
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $share->getExpiration());
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame("Thu, 01 Jan 2060 10:00:00 +0000", $share->getExpiration()->format('r'));
        $this->assertSame("Z", $share->getExpiration()->getTimezone()->getName());
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(1, $createdShares);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiration());
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame("Thu, 01 Jan 2060 10:00:00 +0000", $createdShares[0]->getExpiration()->format('r'));
        $this->assertSame("Z", $createdShares[0]->getExpiration()->getTimezone()->getName());
    }

    public function testGetReceiversOfShareCreatedByInvite(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = [];
        $shares[] = $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $shares[] = $this->fileToShare->invite($this->marie, $this->viewerRole);
        $shares[] = $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->assertSame("Albert Einstein", $shares[0]->getReceiver()->getDisplayName());
        $this->assertSame("Marie Curie", $shares[1]->getReceiver()->getDisplayName());
        $this->assertSame("philosophyhaters", $shares[2]->getReceiver()->getDisplayName());
    }

    public function testGetReceiversOfShareReturnedBySharedByMe(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group'
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
        $this->assertCount(6, $shares);
        for($i = 0; $i < 6; $i++) {
            $this->assertInstanceOf(ShareCreated::class, $shares[$i]);
            $this->assertSame($this->personalDrive->getId(), $shares[$i]->getDriveId());
            $this->assertThat(
                $shares[$i]->getResourceId(),
                $this->logicalOr(
                    $this->equalTo($this->folderToShare->getId()),
                    $this->equalTo($this->fileToShare->getId())
                )
            );
            $this->assertThat(
                $shares[$i]->getReceiver()->getDisplayName(),
                $this->logicalOr(
                    $this->equalTo("philosophyhaters"),
                    $this->equalTo("Marie Curie"),
                    $this->equalTo("Albert Einstein")
                )
            );

        }
    }

    public function testInviteUserToAReceivedShare(): void
    {
        $this->fileToShare->invite($this->einstein, $this->managerRole);
        /**
         * @var ShareReceived $receivedShare
         */
        $receivedShare = $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis)[0];

        $resource = $this->einsteinOcis->getResourceById($receivedShare->getRemoteItemId());
        $resource->invite($this->marie, $this->viewerRole);
        /**
         * @var ShareReceived $receivedShare
         */
        $receivedShare = $this->getSharedWithMeWaitTillShareIsAccepted($this->marieOcis)[0];
        $this->assertSame('to-share-test.txt', $receivedShare->getName());
        $this->assertSame(
            'some content',
            $this->marieOcis->getResourceById($receivedShare->getRemoteItemId())->getContent()
        );
    }
}
