<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

class ResourceInviteTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private SharingRole $viewerRole;
    private OcisResource $resourceToShare;
    private Ocis $ocis;
    private Ocis $einsteinOcis;
    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $token = $this->getAccessToken('admin', 'admin');
        $this->ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $resources = $personalDrive->getResources();
        /**
         * @var OcisResource $resource
         */
        foreach ($resources as $resource) {
            if ($resource->getName() === 'to-share-test.txt') {
                $this->resourceToShare = $resource;
                break;
            }
        }

        $this->einstein = $this->ocis->getUsers('einstein')[0];

        // in future this will have to come also from the system, but the permissions API is not implemented yet
        // so we have to create a SharingRole manually
        $this->viewerRole = new SharingRole(
            new UnifiedRoleDefinition(
                [
                    'id' => 'viewer',
                    'display_name' => 'viewer',
                    'description' => 'viewer',
                    'at_libre_graph_weight' => 1,
                ]
            )
        );
    }

    public function testInviteUser(): void
    {
        $shares = $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
        $this->assertCount(1, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteAnotherUser(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $marie = $this->ocis->getUsers('marie')[0];
        $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
        $shares = $this->resourceToShare->invite([$marie], $this->viewerRole);
        $this->assertCount(1, $shares);
        $receivedShares = $marieOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteMultipleUsersAtOnce(): void
    {
        $marieOcis = $this->initUser('marie', 'radioactivity');
        $marie = $this->ocis->getUsers('marie')[0];
        $shares = $this->resourceToShare->invite([$this->einstein,$marie], $this->viewerRole);
        $this->assertCount(2, $shares);
        $receivedShares = $marieOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());

        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = $this->resourceToShare->invite([$philosophyHatersGroup], $this->viewerRole);
        $this->assertCount(1, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(1, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());
    }

    public function testInviteGroupAndUserOfTheGroup(): void
    {
        $philosophyHatersGroup =  $this->ocis->createGroup(
            'philosophy-haters',
            'philosophy haters group'
        );
        $this->createdGroups = [$philosophyHatersGroup];
        $philosophyHatersGroup->addUser($this->einstein);
        $shares = $this->resourceToShare->invite([$philosophyHatersGroup, $this->einstein], $this->viewerRole);
        $this->assertCount(2, $shares);
        $receivedShares = $this->einsteinOcis->getSharedWithMe();
        $this->assertCount(2, $receivedShares);
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[0]->getName());
        $this->assertSame($this->resourceToShare->getName(), $receivedShares[1]->getName());
    }

    public function testInviteSameUserAgain(): void
    {
        $this->markTestSkipped('https://github.com/owncloud/ocis/issues/7842');
        // @phpstan-ignore-next-line because the test is skipped
        $this->expectException(ForbiddenException::class);
        $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
        $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
    }

}
