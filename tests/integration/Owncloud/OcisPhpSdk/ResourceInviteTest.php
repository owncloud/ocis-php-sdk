<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ResourceInviteTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private User $marie;
    private SharingRole $viewerRole;
    private SharingRole $managerRole; // @phpstan-ignore-line the property is used, but in a skipped test
    private OcisResource $fileToShare;
    private OcisResource $folderToShare;
    private Ocis $ocis;
    private Ocis $einsteinOcis;
    private Ocis $marieOcis;
    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->marieOcis = $this->initUser('marie', 'radioactivity');
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $personalDrive->createFolder('folder-to-share');
        $this->createdResources[$personalDrive->getId()][] = 'folder-to-share';
        $resources = $personalDrive->getResources();
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
        $shares = $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $this->assertCount(1, $shares);
        $this->assertNull($shares[0]->getExpiry());
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteAnotherUser(): void
    {
        $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $shares = $this->fileToShare->invite([$this->marie], $this->viewerRole);
        $this->assertCount(1, $shares);
        $receivedShares = $this->marieOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteMultipleUsersAtOnce(): void
    {
        $shares = $this->fileToShare->invite([$this->einstein,$this->marie], $this->viewerRole);
        $this->assertCount(2, $shares);
        $receivedShares = $this->marieOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());

        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = $this->fileToShare->invite([$philosophyHatersGroup], $this->viewerRole);
        $this->assertCount(1, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteGroupAndUserOfTheGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = $this->fileToShare->invite([$philosophyHatersGroup, $this->einstein], $this->viewerRole);
        $this->assertCount(2, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(2, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
        $this->assertSame($this->fileToShare->getName(), $receivedShares[1]->getName());
    }

    public function testInviteMultipleGroups(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $physicsLoversGroup =  $this->ocis->createGroup(
            'physics-lovers',
            'physics lovers group'
        );
        $this->createdGroups = [$philosophyHatersGroup, $physicsLoversGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $physicsLoversGroup->addUser($this->einstein);
        $physicsLoversGroup->addUser($this->marie);
        $shares = $this->fileToShare->invite([$physicsLoversGroup, $philosophyHatersGroup], $this->viewerRole);
        $this->assertCount(2, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(2, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
        $this->assertSame($this->fileToShare->getName(), $receivedShares[1]->getName());

        $receivedShares = $this->marieOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->fileToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteSameUserAgain(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7842');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(ForbiddenException::class);
        $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $this->fileToShare->invite([$this->einstein], $this->viewerRole);
    }

    public function testInviteSameUserAgainWithDifferentRole(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7842');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(ForbiddenException::class);
        $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $this->fileToShare->invite([$this->einstein], $this->managerRole);
    }

    public function testInviteWithExpiry(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $shares = $this->fileToShare->invite([$this->einstein], $this->viewerRole, $tomorrow);
        $this->assertCount(1, $shares);
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(1, $createdShares);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiry());
        $this->assertSame($tomorrow->getTimestamp(), $createdShares[0]->getExpiry()->getTimestamp());
    }

    public function testInviteWithPastExpiry(): void
    {
        $this->expectException(BadRequestException::class);
        $yesterday = new \DateTimeImmutable('yesterday');
        $this->fileToShare->invite([$this->einstein], $this->viewerRole, $yesterday);
    }

    public function testInviteWithExpiryTimezone(): void
    {
        $expiry = new \DateTimeImmutable('2060-01-01 12:00:00', new \DateTimeZone('Europe/Kyiv'));
        $shares = $this->fileToShare->invite([$this->marie], $this->viewerRole, $expiry);
        $this->assertCount(1, $shares);
        $createdShares = $this->ocis->getSharedByMe();
        $this->assertCount(1, $createdShares);
        $this->assertInstanceOf(\DateTimeImmutable::class, $createdShares[0]->getExpiry());
        // The returned expiry is in UTC timezone (2 hours earlier than the expiry time in Kyiv)
        $this->assertSame("Thu, 01 Jan 2060 10:00:00 +0000", $createdShares[0]->getExpiry()->format('r'));
        $this->assertSame("Z", $createdShares[0]->getExpiry()->getTimezone()->getName());
    }

    public function testGetReceiversOfShareCreatedByInvite(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = $this->fileToShare->invite(
            [$this->einstein, $this->marie, $philosophyHatersGroup],
            $this->viewerRole
        );
        $this->assertCount(3, $shares);
        for($i = 0; $i < 3; $i++) {
            $this->assertThat(
                $shares[$i]->getReceiver()->getDisplayName(),
                $this->logicalOr(
                    $this->equalTo("philosophy-haters"),
                    $this->equalTo("Marie Curie"),
                    $this->equalTo("Albert Einstein")
                )
            );
        }
    }

    public function testGetReceiversOfShareReturnedBySharedByMe(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $this->fileToShare->invite(
            [$this->einstein, $this->marie, $philosophyHatersGroup],
            $this->viewerRole
        );
        $this->folderToShare->invite(
            [$this->einstein, $this->marie, $philosophyHatersGroup],
            $this->viewerRole
        );
        $shares = $this->ocis->getSharedByMe();
        $this->assertCount(6, $shares);
        for($i = 0; $i < 6; $i++) {
            $this->assertThat(
                $shares[$i]->getReceiver()->getDisplayName(),
                $this->logicalOr(
                    $this->equalTo("philosophy-haters"),
                    $this->equalTo("Marie Curie"),
                    $this->equalTo("Albert Einstein")
                )
            );
        }
    }
}
