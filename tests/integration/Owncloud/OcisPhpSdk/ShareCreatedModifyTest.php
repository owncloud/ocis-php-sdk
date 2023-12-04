<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\Permission;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ShareCreatedModifyTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private User $marie;
    private SharingRole $viewerRole;
    private OcisResource $fileToShare;
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
        $this->fileToShare = $personalDrive->getResources()[0];

        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->marie = $this->ocis->getUsers('marie')[0];

        /**
         * @var SharingRole $role
         */
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getDisplayName() === 'Viewer') {
                $this->viewerRole = $role;
                break;
            }
        }
    }

    public function testDeleteIndividualShare(): void
    {
        $this->fileToShare->invite([$this->einstein, $this->marie], $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        foreach ($shares as $share) {
            if ($share->getReceiver()->getDisplayName() === 'Albert Einstein') {
                $share->delete();
                break;
            }
        }
        $this->assertCount(1, $this->ocis->getSharedByMe());
        $this->assertCount(0, $this->einsteinOcis->getSharedWithMe());
        $this->assertCount(1, $this->marieOcis->getSharedWithMe());
    }

    public function testDeleteGroupShare(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);

        $this->fileToShare->invite([$this->einstein, $philosophyHatersGroup, $this->marie], $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        foreach ($shares as $share) {
            if ($share->getReceiver()->getDisplayName() === 'philosophy-haters') {
                $share->delete();
                break;
            }
        }
        $this->assertCount(2, $this->ocis->getSharedByMe());
        $this->assertCount(1, $this->einsteinOcis->getSharedWithMe());
        $this->assertCount(1, $this->marieOcis->getSharedWithMe());
    }

    public function testDeleteAnAlreadyDeletedShare(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7872');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(NotFoundException::class);
        $this->fileToShare->invite([$this->einstein, $this->marie], $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        $shares[0]->delete();
        $shares[0]->delete();
    }

    public function testDeleteANotExistingShare(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7872');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(NotFoundException::class);
        $permission = new Permission([
            'id' => 'does not exist'
        ]);
        $token = $this->getAccessToken('admin', 'admin');
        $share = new ShareCreated(
            $permission,
            $this->fileToShare->getId(),
            $this->fileToShare->getDriveId(),
            ['verify' => false],
            $this->ocisUrl,
            $token
        );
        $share->delete();
    }

    public function testSetExpirationDateOnObjectFromInvite(): void
    {
        $this->markTestSkipped('Not implemented yet in oCIS, see https://github.com/owncloud/ocis/issues/6993');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(NotFoundException::class);
        $sharesFromInvite = $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $tomorrow = new \DateTime('tomorrow');
        $sharesFromInvite[0]->setExpiration($tomorrow);
        $sharedByMeShares = $this->ocis->getSharedByMe();

        $this->assertInstanceOf(\DateTimeImmutable::class, $sharesFromInvite[0]->getExpiration());
        $this->assertSame($tomorrow->getTimestamp(), $sharesFromInvite[0]->getExpiration()->getTimestamp());
        $this->assertInstanceOf(\DateTimeImmutable::class, $sharedByMeShares[0]->getExpiration());
        $this->assertSame($tomorrow->getTimestamp(), $sharedByMeShares[0]->getExpiration()->getTimestamp());
    }

    public function testSetExpirationDateOnObjectFromSharedByMe(): void
    {
        $this->markTestSkipped('Not implemented yet in oCIS, see https://github.com/owncloud/ocis/issues/6993');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(NotFoundException::class);
        $this->fileToShare->invite([$this->einstein], $this->viewerRole);
        $tomorrow = new \DateTime('tomorrow');
        $sharedByMeShares = $this->ocis->getSharedByMe();
        $sharedByMeShares[0]->setExpiration($tomorrow);

        $this->assertInstanceOf(\DateTimeImmutable::class, $sharedByMeShares[0]->getExpiration());
        $this->assertSame($tomorrow->getTimestamp(), $sharedByMeShares[0]->getExpiration()->getTimestamp());
    }
}
