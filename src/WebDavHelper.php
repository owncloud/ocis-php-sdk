<?php

namespace Owncloud\OcisSdkPhp;

use Sabre\DAV\Client;

class WebDavHelper
{
    public const SPACES_WEBDAV_PATH = '/dav/spaces/';

    /**
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @throws \Exception
     */
    public static function createWebDavClient(
        string $baseUrl,
        array $connectionConfig,
        string $accessToken
    ): Client {
        $webDavClient = new Client(['baseUri' => $baseUrl]);
        $curlSettings = self::createCurlSettings($connectionConfig, $accessToken);
        foreach ($curlSettings as $setting => $value) {
            $webDavClient->addCurlSetting($setting, $value);
        }
        $webDavClient->setThrowExceptions(true);
        return $webDavClient;
    }

    /**
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @return array<int, mixed>
     * @throws \Exception
     */
    public static function createCurlSettings(array $connectionConfig, string $accessToken): array
    {
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \Exception('connection configuration not valid');
        }
        $settings = [];
        $settings[CURLOPT_HTTPAUTH] = CURLAUTH_BEARER;
        $settings[CURLOPT_XOAUTH2_BEARER] = $accessToken;
        if (isset($connectionConfig['headers'])) {
            foreach ($connectionConfig['headers'] as $header => $value) {
                $settings[CURLOPT_HTTPHEADER][] = $header . ': ' . $value;
            }
        }
        if (isset($connectionConfig['verify'])) {
            $settings[CURLOPT_SSL_VERIFYPEER] = $connectionConfig['verify'];
            $settings[CURLOPT_SSL_VERIFYHOST] = $connectionConfig['verify'];
        }
        return $settings;
    }
}
