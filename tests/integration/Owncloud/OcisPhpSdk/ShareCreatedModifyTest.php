<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\Permission;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
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
        $this->ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($this->ocis);


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $this->fileToShare = $personalDrive->getResources()[0];

        $this->einstein = $this->ocis->getUsers('einstein')[0];
        $this->marie = $this->ocis->getUsers('marie')[0];

        $viewerRoleId = self::getPermissionsRoleIdByName('Viewer');
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getId() === $viewerRoleId) {
                $this->viewerRole = $role;
                break;
            }
        }
        $this->assertNotNull($this->viewerRole, 'Viewer role is not set');
    }

    public function testDeleteIndividualShare(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->marie, $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        foreach ($shares as $share) {
            $this->assertInstanceOf(
                ShareCreated::class,
                $share,
                "Expected class to be 'ShareCreated' but found "
                . get_class($share),
            );
            if ($share->getReceiver()->getDisplayName() === 'Albert Einstein') {
                $share->delete();
                break;

            }
        }
        $this->assertCount(
            1,
            $this->ocis->getSharedByMe(),
            "Expected count of Shared resource doesn't match",
        );
        $this->assertCount(
            0,
            $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis),
            "Failed to unshare resource to Einstein",
        );
        $this->assertCount(
            1,
            $this->getSharedWithMeWaitTillShareIsAccepted($this->marieOcis),
            "Expected shared resource for marie be 1 but found " . count($this->marieOcis->getSharedWithMe()),
        );
    }

    public function testDeleteGroupShare(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophyhaters',
            'philosophy haters group',
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);

        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($philosophyHatersGroup, $this->viewerRole);
        $this->fileToShare->invite($this->marie, $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        foreach ($shares as $share) {
            $this->assertInstanceOf(
                ShareCreated::class,
                $share,
                "Expected class to be 'ShareCreated' but found "
                . get_class($share),
            );
            if ($share->getReceiver()->getDisplayName() === 'philosophyhaters') {
                $share->delete();
                break;
            }
        }
        $this->assertCount(
            2,
            $this->ocis->getSharedByMe(),
            "Expected shared resource count to be 2 but found " . count($this->ocis->getSharedByMe()),
        );
        $this->assertCount(
            1,
            $this->getSharedWithMeWaitTillShareIsAccepted($this->einsteinOcis),
            "Shared resources was unshared to the group",
        );
        $this->assertCount(
            1,
            $this->getSharedWithMeWaitTillShareIsAccepted($this->marieOcis),
            "Expected shared resource count to be 1 but found " . count($this->marieOcis->getSharedWithMe()),
        );
    }

    public function testDeleteAnAlreadyDeletedShare(): void
    {
        $this->expectException(NotFoundException::class);
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $this->fileToShare->invite($this->marie, $this->viewerRole);
        $shares = $this->ocis->getSharedByMe();
        $shares[0]->delete();
        $shares[0]->delete();
    }

    public function testDeleteANotExistingShare(): void
    {
        $this->expectException(NotFoundException::class);
        $permission = new Permission([
            'id' => 'does not exist',
        ]);
        $token = $this->getAccessToken('admin', 'admin');
        $share = new ShareCreated(
            $permission,
            $this->fileToShare->getId(),
            $this->fileToShare->getSpaceId(),
            ['verify' => false],
            $this->ocisUrl,
            $token,
        );
        $share->delete();
    }

    public function testSetExpirationDateOnObjectFromInvite(): void
    {
        $shareFromInvite = $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $shareFromInvite->setExpiration($tomorrow);
        $expirationDateTime = $shareFromInvite->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $expirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($expirationDateTime, true),
        );
        $this->assertSame(
            $tomorrow->getTimestamp(),
            $expirationDateTime->getTimestamp(),
            "Expected timestamp of shared resource to be " . $tomorrow->getTimestamp() . " but found "
            . $expirationDateTime->getTimestamp(),
        );
    }

    public function testSetExpirationDateOnObjectFromSharedByMe(): void
    {
        $this->fileToShare->invite($this->einstein, $this->viewerRole);
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $sharedByMeShares = $this->ocis->getSharedByMe();
        $sharedByMeShares[0]->setExpiration($tomorrow);
        $expirationDateTime = $sharedByMeShares[0]->getExpiration();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $expirationDateTime,
            "Expected class to be 'DateTimeImmutable' but found "
            . print_r($expirationDateTime, true),
        );
        $this->assertSame(
            $tomorrow->getTimestamp(),
            $expirationDateTime->getTimestamp(),
            "Expected timestamp of shared resource to be " . $tomorrow->getTimestamp() . " but found "
            . $expirationDateTime->getTimestamp(),
        );
    }

    public function testUpdateExpirationDate(): void
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $shareFromInvite = $this->fileToShare->invite($this->einstein, $this->viewerRole, $tomorrow);

        $oneYearTime = new \DateTimeImmutable(date('Y-m-d', strtotime('+1 year')));
        $shareFromInvite->setExpiration($oneYearTime);
        $sharedByMeShares = $this->ocis->getSharedByMe();
        $this->assertEquals(
            $shareFromInvite->getExpiration(),
            $sharedByMeShares[0]->getExpiration(),
            "Expected DateTime of shared resources from Sharer and Receiver doesn't match",
        );
    }

    public function testSetRole(): void
    {
        $shareFromInvite = $this->fileToShare->invite($this->einstein, $this->viewerRole);

        $isRoleEditor = null;
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getId() === self::getPermissionsRoleIdByName('File Editor')) {
                $isRoleEditor = $shareFromInvite->setRole($role);
                break;
            }
        }
        $this->assertTrue($isRoleEditor, "Failed to set role");
    }

}
