<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\Model\CollectionOfPermissions;
use OpenAPI\Client\Model\DriveItemInvite;
use OpenAPI\Client\Model\DriveRecipient;
use OpenAPI\Client\Model\Group as OpenAPIGroup;
use OpenAPI\Client\Model\User as OpenAPIUser;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\UnifiedRoleDefinition;
use Owncloud\OcisPhpSdk\Group;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\ShareCreated;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;
use PHPUnit\Framework\TestCase;

class ResourceInviteTest extends TestCase
{
    /**
     * @return array<mixed>
     */
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
        $accessToken = "acstok";
        $smartPeopleGroup = new Group($openAPIGroup, "url", [], $accessToken);

        return [
            // invite for a single recipient
            [
                [$einstein],
                null,
                new DriveItemInvite(
                    [
                        'recipients' => [
                            new DriveRecipient(
                                [
                                    'object_id' => 'uuid-of-einstein',
                                ]
                            ),
                        ],
                        'roles' => ['uuid-of-the-role'],
                    ]
                )
            ],
            // invite a user and a group
            [
                [$einstein, $smartPeopleGroup],
                null,
                new DriveItemInvite(
                    [
                        'recipients' => [
                            new DriveRecipient(
                                [
                                    'object_id' => 'uuid-of-einstein',
                                ]
                            ),
                            new DriveRecipient(
                                [
                                    'object_id' => 'uuid-of-smart-people-group',
                                    'at_libre_graph_recipient_type' => 'group',
                                ]
                            ),
                        ],
                        'roles' => ['uuid-of-the-role'],
                    ]
                )
            ],
            // set expiry time
            [
                [$smartPeopleGroup],
                new \DateTime('2022-12-31 01:02:03.456789'),
                new DriveItemInvite(
                    [
                        'recipients' => [
                            new DriveRecipient(
                                [
                                    'object_id' => 'uuid-of-smart-people-group',
                                    'at_libre_graph_recipient_type' => 'group',
                                ]
                            ),
                        ],
                        'roles' => ['uuid-of-the-role'],
                        'expiration_date_time' => '2022-12-31T01:02:03:456789Z'
                    ]
                )
            ],
            // set expiry time, with conversion to UTC/Z timezone
            [
                [$einstein],
                new \DateTime('2021-01-01 17:45:43.123456', new \DateTimeZone('Asia/Kathmandu')),
                new DriveItemInvite(
                    [
                        'recipients' => [
                            new DriveRecipient(
                                [
                                    'object_id' => 'uuid-of-einstein',
                                ]
                            ),
                        ],
                        'roles' => ['uuid-of-the-role'],
                        'expiration_date_time' => '2021-01-01T12:00:43:123456Z'
                    ]
                )
            ],
        ];
    }

    /**
     * @dataProvider inviteDataProvider
     * @param array<int, User|Group> $recipients
     */
    public function testInvite($recipients, ?\DateTime $expiration, DriveItemInvite $expectedInviteData): void
    {
        $permission = $this->createMock(Permission::class);
        $permission->method('getId')
            ->willReturn('uuid-of-the-permission');
        $permissions = $this->createMock(CollectionOfPermissions::class);
        $permissions->method('getValue')
            ->willReturn([$permission]);
        $drivesPermissionsApi = $this->createMock(DrivesPermissionsApi::class);
        $drivesPermissionsApi->method('invite')
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            ->with('uuid-of-the-drive', 'uuid-of-the-resource', $expectedInviteData)
            ->willReturn($permissions);
        $accessToken = 'an-access-token';
        $connectionConfig = [
            'drivesPermissionsApi' => $drivesPermissionsApi,
        ];
        $resourceMetadata = [
            '{http://owncloud.org/ns}id' => 'uuid-of-the-resource',
        ];

        $resource = new OcisResource(
            $resourceMetadata,
            'uuid-of-the-drive',
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
        $this->assertContainsOnly(ShareCreated::class, $result);
    }
}
