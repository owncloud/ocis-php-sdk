<?php

namespace unit\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\WebDavClient;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type ConnectionConfig from \Owncloud\OcisPhpSdk\Ocis
 */
class WebDavClientTest extends TestCase
{
    /**
     * @return array<int, array<int, array<mixed>|string>>
     */
    public static function connectionConfigProvider(): array
    {
        return [
            [[], 'https://ocis.sdk.tests:9009', [CURLOPT_HTTPAUTH => CURLAUTH_BEARER, CURLOPT_XOAUTH2_BEARER => 'token']],
            [['headers' => ['X-Header' => 'X-value', 'Y-Header' => 'Y-value']],
                'https://ocis.sdk.tests:9009',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_HTTPHEADER => ['X-Header: X-value', 'Y-Header: Y-value'],
                ],
            ],
            [
                ['verify' => false],
                'https://ocis.sdk.tests:9009',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ],
            ],
            [
                ['proxy' => 'http://proxy'],
                'https://ocis.sdk.tests:9009',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_PROXY => 'http://proxy',
                ],
            ],
            [
                ['proxy' => ['http' => 'http://proxy', 'https' => 'https://sslproxy']],
                'https://ocis.sdk.tests:9009',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_PROXY => 'https://sslproxy',
                ],
            ],
            [
                ['proxy' => ['http' => 'http://proxy', 'https' => 'https://sslproxy']],
                'http://ocis.sdk.tests:9009',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                    CURLOPT_PROXY => 'http://proxy',
                ],
            ],
            [
                ['proxy' =>
                    [
                        'http' => 'http://proxy',
                        'https' => 'https://sslproxy',
                        'no' => ['no-proxy', 'also-no-proxy']],
                ],
                'http://no-proxy',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                ],
            ],
            [
                ['proxy' =>
                    [
                        'http' => 'http://proxy',
                        'https' => 'https://sslproxy',
                        'no' => ['no-proxy', 'also-no-proxy']],
                ],
                'https://also-no-proxy',
                [
                    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
                    CURLOPT_XOAUTH2_BEARER => 'token',
                ],
            ],
        ];
    }

    /**
     * @phpstan-param ConnectionConfig $connectionConfig
     * @param array<mixed> $expectedCurlSettingsArray
     * @dataProvider connectionConfigProvider
     */
    public function testCreateCurlSettings(
        array $connectionConfig,
        string $baseUri,
        array $expectedCurlSettingsArray,
    ): void {
        $accessToken = 'token';
        $webDavClient = new WebDavClient(['baseUri' => $baseUri]);

        $curlSettings = $webDavClient->createCurlSettings(
            $connectionConfig,
            $accessToken,
        );
        $this->assertSame($expectedCurlSettingsArray, $curlSettings);
    }
}
