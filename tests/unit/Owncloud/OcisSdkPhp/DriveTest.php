<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\Drive;
use PHPUnit\Framework\TestCase;
use OpenAPI\Client\Model\Drive as ApiDrive;

class DriveTest extends TestCase
{
    /**
     * @return array<int, array<int, array<mixed>>>
     */
    public function connectionConfigProvider(): array
    {
        return [
            [[], [CURLOPT_HTTPAUTH => CURLAUTH_BEARER, CURLOPT_XOAUTH2_BEARER => 'token']],
            [['headers' => ['X-Header' => 'X-value', 'Y-Header' => 'Y-value']],
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_HTTPHEADER => ['X-Header: X-value', 'Y-Header: Y-value']
                ]
            ],
            [
                ['verify' => false],
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]
            ]
        ];
    }

    /**
     * @phpstan-param array{'headers':array<string, mixed>, 'verify':bool} $connectionConfig
     * @param array<mixed> $expectedCurlSettingsArray
     * @return void
     * @throws \Exception
     * @dataProvider connectionConfigProvider
     */
    public function testCreateCurlSettings(array $connectionConfig, array $expectedCurlSettingsArray)
    {
        $accessToken = 'token';
        $drive = new Drive(
            /* @phan-suppress-next-line PhanTypeMismatchArgument */
            $this->createMock(ApiDrive::class),
            $connectionConfig,
            $accessToken
        );
        $curlSettings = $drive->createCurlSettings();
        $this->assertSame($expectedCurlSettingsArray, $curlSettings);
    }
}
