<?php

namespace unit\Owncloud\OcisPhpSdk;

use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\CollectionOfDrives;
use OpenAPI\Client\Model\Drive;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\OdataErrorMain;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OcisTest extends TestCase
{
    public function testCreateGuzzleConfigDefaultValues(): void
    {
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token']
            ],
            Ocis::createGuzzleConfig([], 'token')
        );
    }

    public function testCreateGuzzleConfigVerifyFalse(): void
    {
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token'],
                'verify' => false
            ],
            Ocis::createGuzzleConfig(['verify' => false], 'token')
        );
    }

    public function testCreateGuzzleConfigExtraHeader(): void
    {
        $this->assertEquals(
            [
                'headers' => [
                    'Authorization' => 'Bearer token',
                    'X-something' => 'X-Data'
                ]
            ],
            Ocis::createGuzzleConfig(['headers' => ['X-something' => 'X-Data']], 'token')
        );
    }

    public function testCreateDriveWithInvalidQuota(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $ocis = new Ocis('https://localhost:9200', 'doesNotMatter');
        $ocis->createDrive('driveName', -1);
    }

    public function testCreateDriveReturnsOdataError(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Drive could not be created. 'something went wrong");
        $ocis = new Ocis('https://localhost:9200', 'doesNotMatter');
        $createDriveMock = $this->createMock(DrivesApi::class);
        assert($createDriveMock instanceof DrivesApi);
        $error = (new OdataError())
                ->setError(new OdataErrorMain(['message' => 'something went wrong']));
        $createDriveMock->method('createDrive')
                        ->willReturn($error);
        $ocis->setDrivesApiInstance($createDriveMock);
        $ocis->createDrive('driveName');
    }

    public function testCreateDriveUnresolvedHost(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "[0] cURL error 6: Could not resolve host: localhost-does-not-exist (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://localhost-does-not-exist:9200/graph/v1.0/drives"
        );
        $ocis = new Ocis('https://localhost-does-not-exist:9200', 'doesNotMatter');
        $ocis->createDrive('driveName');
    }

    public function testCreateDriveForbidden(): void
    {
        $this->expectException(ForbiddenException::class);
        $ocis = new Ocis('https://localhost:9200', 'doesNotMatter');
        $createDriveMock = $this->createMock(DrivesApi::class);
        assert($createDriveMock instanceof DrivesApi);
        $createDriveMock->method('createDrive')
            ->willThrowException(new ApiException('forbidden', 403));
        $ocis->setDrivesApiInstance($createDriveMock);
        $ocis->createDrive('driveName');
    }

    public function testSetAccessTokenPropagatesToDrives(): void
    {
        $ocis = new Ocis('https://localhost:9200', 'tokenWhenCreated');
        $driveMock = [];
        $driveMock[] = $this->createMock(Drive::class);
        $driveMock[] = $this->createMock(Drive::class);
        $driveCollectionMock = $this->createMock(CollectionOfDrives::class);
        $driveCollectionMock->method('getValue')
            ->willReturn($driveMock);
        $drivesGetDrivesApi = $this->createMock(DrivesGetDrivesApi::class);
        assert($drivesGetDrivesApi instanceof DrivesGetDrivesApi);
        $drivesGetDrivesApi->method('listAllDrives')
            ->willReturn($driveCollectionMock);
        $ocis->setDrivesGetDrivesApiInstance($drivesGetDrivesApi);
        $drives = $ocis->listAllDrives();
        foreach ($drives as $drive) {
            $this->assertEquals('tokenWhenCreated', $drive->getAccessToken());
        }
        $ocis->setAccessToken('changedToken');
        foreach ($drives as $drive) {
            $this->assertEquals('changedToken', $drive->getAccessToken());
        }
    }

    public function testSetAccessTokenPropagatesToNotifications(): void
    {
        $ocis = $this->setupMocksForNotificationTests(
            '{"ocs":{"data":[{"notification_id":"123"},{"notification_id":"456"}]}}',
            'tokenWhenCreated'
        );
        $notifications = $ocis->getNotifications();
        $this->assertEquals('tokenWhenCreated', $notifications[0]->getAccessToken());
        $this->assertEquals('123', $notifications[0]->getId());
        $this->assertEquals('tokenWhenCreated', $notifications[1]->getAccessToken());
        $this->assertEquals('456', $notifications[1]->getId());
        $ocis->setAccessToken('changedToken');
        $this->assertEquals('changedToken', $notifications[0]->getAccessToken());
        $this->assertEquals('changedToken', $notifications[1]->getAccessToken());
    }

    private function setupMocksForNotificationTests(
        string $responseContent,
        string $token = 'doesNotMatter'
    ): Ocis {
        $ocis = new Ocis('https://localhost:9200', $token);
        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($responseContent);
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getBody')->willReturn($streamMock);
        $guzzleMock = $this->createMock(\GuzzleHttp\Client::class);
        $guzzleMock->method('get')->willReturn($responseMock);
        /* @phan-suppress-next-line PhanTypeMismatchArgument */
        $ocis->setGuzzle($guzzleMock);
        return $ocis;
    }

    /**
     * @return array<int, mixed>
     */
    public function invalidJsonNotificationResponse(): array
    {
        return [
            [""],
            ["data,"],
            ["{data:}"]
        ];
    }

    /**
     * @dataProvider invalidJsonNotificationResponse
     */
    public function testGetNotificationResponseNotJson(string $responseContent): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Could not decode notification response. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

    /**
     * @return array<int, mixed>
     */
    public function invalidOcsNotificationResponse(): array
    {
        return [
            ['{"ocs":{"meta":{"message":"","status":"","statuscode":200}}}'],
            ['{"ocs": null}'],
            ['{}'],
            ['{"ocs":{"meta":{"message":"","status":"","statuscode":200},"data":"string"}}']
        ];
    }

    /**
     * @dataProvider invalidOcsNotificationResponse
     */
    public function testGetNotificationInvalidOcsData(
        string $responseContent
    ): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Notification response is invalid. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

    /**
     * @return array<int, mixed>
     */
    public function invalidOrMissingIdInOcsNotificationResponse(): array
    {
        return [
            ['{"ocs":{"data":[{"notification_id":""}]}}'],
            ['{"ocs":{"data":[{"notification_id":123}]}}'],
            ['{"ocs":{"data":[{"notificationId":"123"}]}}']
        ];
    }

    /**
     * @dataProvider invalidOrMissingIdInOcsNotificationResponse
     */
    public function testGetNotificationMissingOrInvalidId(
        string $responseContent
    ): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Id is invalid or missing in notification response. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

    public function testGetNotificationMissingDataInResponse(): void
    {
        $responseContent = '{"ocs":{"data":[{"notification_id":"123"}]}}';
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $notifications = $ocis->getNotifications();
        $this->assertIsString($notifications[0]->getId());
        $this->assertIsString($notifications[0]->getApp());
        $this->assertIsString($notifications[0]->getUser());
        $this->assertIsString($notifications[0]->getDatetime());
        $this->assertIsString($notifications[0]->getObjectId());
        $this->assertIsString($notifications[0]->getObjectType());
        $this->assertIsString($notifications[0]->getSubject());
        $this->assertIsString($notifications[0]->getSubjectRich());
        $this->assertIsString($notifications[0]->getMessage());
        $this->assertIsString($notifications[0]->getMessageRich());
        $this->assertIsArray($notifications[0]->getMessageRichParameters());
    }

    /**
     * @return array<int, mixed>
     */
    public function connectionConfigDataProvider(): array
    {
        return [
            [
                [],
                true
            ],
            [
                ['verify' => false],
                true
            ],
            [
                ['headers' => ['X-something' => 'X-Data']],
                true
            ],
            [
                ['headers' => ['X-something' => 'X-Data', 'X-some-other' => 'X-Data']],
                true
            ],
            [
                ['headers' => 'string'],
                false
            ],
            [
                ['headers' => null],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => false],
                true
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => 'false'],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => 'true'],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => '1'],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => '0'],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => 1],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => 0],
                false
            ],
            [
                ['headers' => ['X-something' => 'X-Data'], 'verify' => true],
                true
            ],
            [
                ['crud' => 'some value'],
                false
            ],
            [
                ['crud' => 'some value', 'verify' => false],
                false
            ],
        ];
    }

    /**
     * @param array<mixed> $connectionConfig
     * @dataProvider connectionConfigDataProvider
     */
    public function testIsConnectionConfigValid(
        array $connectionConfig,
        bool $expectedResult
    ): void {
        $this->assertSame($expectedResult, Ocis::isConnectionConfigValid($connectionConfig));
    }

    /**
     * @return array<int, mixed>
     */
    public function noNotificationsDataProvider(): array
    {
        return [
            ['{"ocs":{"data":[]}}'],
            ['{"ocs":{"data":null}}'],
        ];
    }

    /**
     * @dataProvider noNotificationsDataProvider
     */
    public function testGetNotificationNoNotifications(string $responseContent): void
    {
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $notifications = $ocis->getNotifications();
        $this->assertEquals([], $notifications);
    }
}
