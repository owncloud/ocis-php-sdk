<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\Model\Group as OpenAPIGroup;
use OpenAPI\Client\Model\User as OpenAPIUser;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;
use PHPUnit\Framework\TestCase;

class ResourceInviteTest extends TestCase
{
    public function inviteDataProvider(): array
    {
        $openAPIUser = new OpenAPIUser(
            [
                'id' => 'uuid-of-einstein',
                'display_name' => 'Albert Einstein',
                'mail' => 'einstein@owncloud.np',
                'on_premises_sam_account_name' => 'albert-einstein',
            ]
        );
        $einstein = new User($openAPIUser);

        $openAPIGroup = new OpenAPIGroup(
            [
                'id' => 'uuid-of-smart-people-group',
                'display_name' => 'smart-people',
            ]
        );
        $smartPeopleGroup = new Group($openAPIGroup);

        return [
            // invite for a single recipient
            [[$einstein], null, '{"recipients":[{"objectId":"uuid-of-einstein"}],"roles":["uuid-of-the-role"]}'],
            // invite a user and a group
            [
                [$einstein, $smartPeopleGroup],
                null,
                '{'.
                    '"recipients":' .
                        '[' .
                            '{"objectId":"uuid-of-einstein"},' .
                            '{"objectId":"uuid-of-smart-people-group","@libre.graph.recipient.type":"group"}],' .
                    '"roles":["uuid-of-the-role"]' .
                '}'
            ],
            // invite a user and set expiry time
            [
                [$einstein],
                new \DateTime('2021-01-01 17:45:43.123456', new \DateTimeZone('Asia/Kathmandu')),
                '{' .
                    '"recipients":[{"objectId":"uuid-of-einstein"}],' .
                    '"roles":["uuid-of-the-role"],' .
                    '"expirationDateTime":"2021-01-01T12:00:43:123456Z"' .
                '}'
            ],
        ];
    }

    /**
     * @dataProvider inviteDataProvider
     */
    public function testInvite($recipients, $expiration, $expectedBody)
    {
        $drivesPermissionsApi = $this->createMock(DrivesPermissionsApi::class);
        $drivesPermissionsApi->method('invite')
            ->with('uuid-of-the-space', 'uuid-of-the-resource', $expectedBody)
            ->willReturn($this->createMock(Permission::class));
        $accessToken = 'an-access-token';
        $connectionConfig = [
            'drivesPermissionsApi' => $drivesPermissionsApi,
        ];
        $resourceMetadata = [
            '{http://owncloud.org/ns}id' => 'uuid-of-the-resource',
            '{http://owncloud.org/ns}spaceid' => 'uuid-of-the-space',
        ];

        $resource = new OcisResource(
            $resourceMetadata,
            $connectionConfig,
            'http://ocis',
            $accessToken
        );

        $openAPIRole = new UnifiedRoleDefinition(
            [
                'id' => 'uuid-of-the-role',
                'display_name' => 'Manager'
            ],
        );
        $role = new SharingRole($openAPIRole);

        $result = $resource->invite($recipients, $role, $expiration);
        $this->assertTrue($result);
    }
}
