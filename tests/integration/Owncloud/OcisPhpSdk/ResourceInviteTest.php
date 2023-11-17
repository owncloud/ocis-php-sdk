<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Drive;
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
    public function setUp(): void
    {
        parent::setUp();
        $this->initUser('einstein', 'relativity');
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        /**
         * @var Drive $personalDrive
         */
        $personalDrive = $ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];


        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createsResources[$personalDrive->getId()] = 'to-share-test.txt';
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

        $this->einstein = $ocis->getUsers('einstein')[0];

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

    public function testInvite()
    {
        $result = $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
        $this->assertTrue($result);
    }
    public function testInviteSameUserAgain()
    {
        $this->expectException(ForbiddenException::class);
        $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
        $this->resourceToShare->invite([$this->einstein], $this->viewerRole);
    }

}
