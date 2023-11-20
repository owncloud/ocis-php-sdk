<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Api\DrivesPermissionsApi;
use OpenAPI\Client\Model\DriveItemCreateLink;
use OpenAPI\Client\Model\Permission;
use OpenAPI\Client\Model\SharingLink;
use OpenAPI\Client\Model\SharingLinkType;
use Owncloud\OcisPhpSdk\OcisResource;
use PHPUnit\Framework\TestCase;

class ResourceLinkTest extends TestCase
{
    /**
     * @return array<mixed>
     */
    public function createLinkDataProvider(): array
    {
        return [
            // create a view link with minimal data
            [
                SharingLinkType::VIEW,
                null,
                null,
                null,
                new DriveItemCreateLink(
                    [
                        'type' => 'view',
                        'password' => null,
                        'expiration_date_time' => null,
                        'display_name' => null
                    ]
                )
            ],
            // create a link setting all data
            [
                SharingLinkType::EDIT,
                new \DateTime('2022-12-31 01:02:03.456789'),
                'a-password',
                'the name of the link',
                new DriveItemCreateLink(
                    [
                        'type' => 'edit',
                        'password' => 'a-password',
                        'expiration_date_time' => '2022-12-31T01:02:03:456789Z',
                        'display_name' => 'the name of the link'
                    ]
                )
            ],
            // set expiry time, with conversion to UTC/Z timezone
            [
                SharingLinkType::EDIT,
                new \DateTime('2021-01-01 04:45:43.123456', new \DateTimeZone('Asia/Kathmandu')),
                null,
                null,
                new DriveItemCreateLink(
                    [
                        'type' => 'edit',
                        'password' => null,
                        'expiration_date_time' => '2020-12-31T23:00:43:123456Z',
                        'display_name' => null
                    ]
                )
            ],
        ];
    }

    /**
     * @dataProvider createLinkDataProvider
     */
    public function testCreateLink(
        SharingLinkType $type,
        ?\DateTime $expiration,
        ?string $password,
        ?string $displayName,
        DriveItemCreateLink $expectedCreateLinkData
    ): void {
        $permissionMock = $this->createMock(Permission::class);
        $permissionMock->method('getId')
            ->willReturn('uuid-of-the-permission');
        $linkMock = $this->createMock(SharingLink::class);
        $linkMock->method('getWebUrl')
            ->willReturn('https://ocis.example.com/s/uuid-of-the-link');
        $linkMock->method('getType')
            ->willReturn(SharingLinkType::VIEW);
        $permissionMock->method('getLink')
            ->willReturn($linkMock);

        $drivesPermissionsApi = $this->createMock(DrivesPermissionsApi::class);
        $drivesPermissionsApi->method('createLink')
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            ->with('uuid-of-the-resource', 'uuid-of-the-resource', $expectedCreateLinkData)
            ->willReturn($permissionMock);
        $accessToken = 'an-access-token';
        $connectionConfig = [
            'drivesPermissionsApi' => $drivesPermissionsApi,
        ];
        $resourceMetadata = [
            '{http://owncloud.org/ns}id' => 'uuid-of-the-resource',
        ];

        $resource = new OcisResource(
            $resourceMetadata,
            $connectionConfig,
            'http://ocis',
            $accessToken
        );

        $result = $resource->createLink($type, $expiration, $password, $displayName);
        $this->assertEquals('https://ocis.example.com/s/uuid-of-the-link', $result->getWebUrl());
        // TODO more assertions
    }
}
