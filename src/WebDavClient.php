<?php

namespace Owncloud\OcisPhpSdk;

use Sabre\DAV\Client;
use Sabre\HTTP\Request;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;

class WebDavClient extends Client
{
    /**
     * @param string $method
     * @param string $url
     * @param string|resource|null $body
     * @param array<string, mixed> $headers
     *
     * @return ResponseInterface
     *
     */
    public function webDavRequest(string $method, string $url = '', $body = null, array $headers = []): ResponseInterface
    {
        $url = $this->getAbsoluteUrl($url);
        try {
            $response = $this->send(new Request($method, $url, $headers, $body));
        } catch (SabreClientHttpException|SabreClientException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
        return $response;
    }

    /**
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     * @return array<int, mixed>
     * @throws \Exception
     */
    public function createCurlSettings(array $connectionConfig, string $accessToken): array
    {
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

    /**
     * set curl settings
     * enable exceptions for send method
     * @phpstan-param array{'headers'?:array<string, mixed>, 'verify'?:bool} $connectionConfig
     *
     * @return void
     */
    public function setCustomSetting(array $connectionConfig, string $accessToken): void
    {
        $this->setThrowExceptions(true);
        $curlSettings = $this->createCurlSettings($connectionConfig, $accessToken);
        foreach ($curlSettings as $setting => $value) {
            $this->addCurlSetting($setting, $value);
        }
    }
}
