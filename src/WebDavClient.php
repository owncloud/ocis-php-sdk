<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Utils as GuzzleUtils;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Sabre\DAV\Client;
use Sabre\HTTP\Request;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;

/**
 * @ignore This is only used for internal purposes and should not show up in the documentation
 */
class WebDavClient extends Client
{
    /**
     * @param string $method
     * @param string $url
     * @param string|resource|null $body
     * @param array<string, mixed> $headers
     *
     * @return ResponseInterface
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws InternalServerErrorException
     */
    public function sendRequest(string $method, string $url = '', $body = null, array $headers = []): ResponseInterface
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
     * @return array<int, mixed>
     */
    private function getCurlAuthSettings(string $accessToken): array
    {
        $settings = [];
        $settings[CURLOPT_HTTPAUTH] = CURLAUTH_BEARER;
        $settings[CURLOPT_XOAUTH2_BEARER] = $accessToken;
        return $settings;
    }

    /**
     * @phpstan-param array{
     *                       'headers'?:array<string, mixed>,
     *                      } $connectionConfig
     * @return array<int, mixed>
     */
    private function getCurlHeaders(array $connectionConfig): array
    {
        $settings = [];
        if (isset($connectionConfig['headers'])) {
            foreach ($connectionConfig['headers'] as $header => $value) {
                $settings[CURLOPT_HTTPHEADER][] = $header . ': ' . $value;
            }
        }
        return $settings;
    }

    /**
     * @phpstan-param array{
     *                       'verify'?:bool
     *                      } $connectionConfig
     * @return array<int, mixed>
     */
    private function getCurlVerifySettings(array $connectionConfig): array
    {
        $settings = [];
        if (isset($connectionConfig['verify'])) {
            $settings[CURLOPT_SSL_VERIFYPEER] = $connectionConfig['verify'];
            $settings[CURLOPT_SSL_VERIFYHOST] = $connectionConfig['verify'];
        }
        return $settings;
    }

    /**
     * @phpstan-param array{
     *                      'proxy'?:array{'http'?:string, 'https'?:string, 'no'?:array<string>}|string,
     *                     } $connectionConfig
     * @return array<int, mixed>
     */
    private function getCurlProxySettings(array $connectionConfig): array
    {
        $settings = [];
        // set the proxy settings, basically same as done in guzzle
        if (isset($connectionConfig['proxy'])) {
            $scheme = parse_url($this->baseUri, PHP_URL_SCHEME);

            if (is_string($connectionConfig['proxy'])) {
                $settings[CURLOPT_PROXY] = $connectionConfig['proxy'];
            } elseif (
                array_key_exists('proxy', $connectionConfig) &&
                is_array($connectionConfig['proxy'])
            ) {
                if (isset($connectionConfig['proxy'][$scheme])) {
                    $host = parse_url($this->baseUri, PHP_URL_HOST);
                    if (isset($connectionConfig['proxy']['no']) &&
                        is_string($host) &&
                        GuzzleUtils::isHostInNoProxy($host, $connectionConfig['proxy']['no'])) {
                        // @phpstan-ignore-next-line unsetting an item that does not exist in array does work
                        unset($settings[CURLOPT_PROXY]);
                    } else {
                        // we have already checked if 'proxy' item exists and is an array and that $scheme is set
                        // @phan-suppress-next-next-line PhanTypeArraySuspiciousNullable
                        // @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
                        $settings[CURLOPT_PROXY] = $connectionConfig['proxy'][$scheme];
                    }
                }
            }
        }
        return $settings;
    }

    /**
     * @phpstan-param array{
     *                      'headers'?:array<string, mixed>,
     *                      'proxy'?:array{'http'?:string, 'https'?:string, 'no'?:array<string>}|string,
     *                      'verify'?:bool,
     *                      'webfinger'?:bool,
     *                      'guzzle'?:\GuzzleHttp\Client
     *                     } $connectionConfig
     * @return array<int, mixed>
     */
    public function createCurlSettings(array $connectionConfig, string $accessToken): array
    {
        $settings = [];
        $settings = $settings + $this->getCurlAuthSettings($accessToken);
        $settings = $settings + $this->getCurlHeaders($connectionConfig);
        $settings = $settings + $this->getCurlVerifySettings($connectionConfig);
        $settings = $settings + $this->getCurlProxySettings($connectionConfig);
        return $settings;
    }

    /**
     * set curl settings
     * enable exceptions for send method
     * @phpstan-param array{
     *                       'headers'?:array<string, mixed>,
     *                       'proxy'?:array{'http'?:string, 'https'?:string, 'no'?:array<string>}|string,
     *                       'verify'?:bool,
     *                       'webfinger'?:bool,
     *                       'guzzle'?:\GuzzleHttp\Client
     *                      } $connectionConfig     *
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
