<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\WebDavHelper;
use PHPUnit\Framework\TestCase;

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
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @param array<mixed> $expectedCurlSettingsArray
     * @throws \Exception
     * @dataProvider connectionConfigProvider
     */
    public function testCreateCurlSettings(array $connectionConfig, array $expectedCurlSettingsArray): void
    {
        $accessToken = 'token';
        $curlSettings = WebDavHelper::createCurlSettings(
            $connectionConfig,
            $accessToken
        );
        $this->assertSame($expectedCurlSettingsArray, $curlSettings);
    }
}
