<?php

namespace unit\Owncloud\OcisSdkPhp;

use OpenAPI\Client\Api\DrivesApi;
use OpenAPI\Client\Api\DrivesGetDrivesApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\CollectionOfDrives;
use OpenAPI\Client\Model\Drive;
use OpenAPI\Client\Model\OdataError;
use OpenAPI\Client\Model\OdataErrorMain;
use Owncloud\OcisSdkPhp\ForbiddenException;
use Owncloud\OcisSdkPhp\Ocis;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OcisTest extends TestCase
{
    public function testCreateGuzzleConfigDefaultValues()
    {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token']
            ],
            Ocis::createGuzzleConfig([], 'token')
        );
    }

    public function testCreateGuzzleConfigVerifyFalse()
    {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token'],
                'verify' => false
            ],
            Ocis::createGuzzleConfig(['verify' => false], 'token')
        );
    }

    public function testCreateGuzzleConfigExtraHeader()
    {
        $ocis = new Ocis('http://something', 'token');
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
    public function testCreateDriveWithInvalidQuota()
    {
        $this->expectException(\InvalidArgumentException::class);
        $ocis = new Ocis('https://localhost:9200', 'doesnotmatter');
        $ocis->createDrive('drivename', -1);
    }

    public function testCreateDriveReturnsOdataError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Drive could not be created. 'something went wrong");
        $ocis = new Ocis('https://localhost:9200', 'doesnotmatter');
        $createDriveMock = $this->createMock(DrivesApi::class);
        assert($createDriveMock instanceof DrivesApi);
        $error = (new OdataError())
                ->setError(new OdataErrorMain(['message' => 'something went wrong']));
        $createDriveMock->method('createDrive')
                        ->willReturn($error);
        $ocis->setApiInstance($createDriveMock);
        $ocis->createDrive('drivename');
    }

    public function testCreateDriveUnresolvedHost()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "[0] cURL error 6: Could not resolve host: localhost-does-not-exist (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://localhost-does-not-exist:9200/graph/v1.0/drives"
        );
        $ocis = new Ocis('https://localhost-does-not-exist:9200', 'doesnotmatter');
        $ocis->createDrive('drivename');
    }

    public function testCreateDriveForbidden()
    {
        $this->expectException(ForbiddenException::class);
        $ocis = new Ocis('https://localhost:9200', 'doesnotmatter');
        $createDriveMock = $this->createMock(DrivesApi::class);
        assert($createDriveMock instanceof DrivesApi);
        $createDriveMock->method('createDrive')
            ->willThrowException(new ApiException('forbidden', 403));
        $ocis->setApiInstance($createDriveMock);
        $ocis->createDrive('drivename');
    }

    public function testSetAccessTokenPropagatesToDrives()
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
        $ocis->setApiInstance($drivesGetDrivesApi);
        $drives = $ocis->listAllDrives();
        foreach ($drives as $drive) {
            $this->assertEquals('tokenWhenCreated', $drive->getAccessToken());
        }
        $ocis->setAccessToken('changedToken');
        foreach ($drives as $drive) {
            $this->assertEquals('changedToken', $drive->getAccessToken());
        }
    }

    public function testSetAccessTokenPropagatesToNotifications()
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
    public function invalidJsonNotificationResponse(): array
    {
        return [
            [""],
            ["data,"],
            ["{data:}"]
        ];
    }
    /**
     * @return void
     * @dataProvider invalidJsonNotificationResponse
     */
    public function testGetNotificationResponseNotJson(string $responseContent)
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Could not decode notification response. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

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
     * @return void
     * @dataProvider invalidOcsNotificationResponse
     */
    public function testGetNotificationInvalidOcsData(
        string $responseContent
    ) {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Notification response is invalid. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

    public function invalidOrMissingIdInOcsNotificationResponse(): array
    {
        return [
            ['{"ocs":{"data":[{"notification_id":""}]}}'],
            ['{"ocs":{"data":[{"notification_id":123}]}}'],
            ['{"ocs":{"data":[{"notificationId":"123"}]}}']
        ];
    }
    /**
     * @return void
     * @dataProvider invalidOrMissingIdInOcsNotificationResponse
     */
    public function testGetNotificationMissingOrInvalidId(
        string $responseContent
    ) {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Id is invalid or missing in notification response. Content: "' . $responseContent . '"'
        );
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $ocis->getNotifications();
    }

    /**
     * @return void
     */
    public function testGetNotificationMissingDataInResponse()
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
    public function testGetNotificationNoNotifications(string $responseContent)
    {
        $ocis = $this->setupMocksForNotificationTests($responseContent);
        $notifications = $ocis->getNotifications();
        $this->assertEquals([], $notifications);
    }
}
