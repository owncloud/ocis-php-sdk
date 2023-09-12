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

class OcisTest extends TestCase
{
    public function testCreateGuzzleConfigDefaultValues()
    {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token']
            ],
            $ocis->createGuzzleConfig()
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
            $ocis->createGuzzleConfig(['verify' => false])
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
            $ocis->createGuzzleConfig(['headers' => ['X-something' => 'X-Data']])
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
}
