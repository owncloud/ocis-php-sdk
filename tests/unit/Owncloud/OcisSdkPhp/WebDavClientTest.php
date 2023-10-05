<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\WebDavClient;
use PHPUnit\Framework\TestCase;

class WebDavClientTest extends TestCase
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
     * @dataProvider connectionConfigProvider
     */
    public function testCreateCurlSettings(array $connectionConfig, array $expectedCurlSettingsArray): void
    {
        $accessToken = 'token';
        $webDavClient = new WebDavClient(['baseUri' => 'https://ocis.sdk.tests:9009']);
        $curlSettings = $webDavClient->createCurlSettings(
            $connectionConfig,
            $accessToken
        );
        $this->assertSame($expectedCurlSettingsArray, $curlSettings);
    }
}
