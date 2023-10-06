<?php

namespace Owncloud\OcisPhpSdk\Exception;

use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\ApiException;
use Sabre\HTTP\ClientException as SabreClientException;
use Sabre\HTTP\ClientHttpException as SabreClientHttpException;

/**
 * @ignore This is only used for internal purposes and should not show up in the documentation
 * Helper class to help with handling different exceptions thrown by dependencies
 */
class ExceptionHelper
{
    /**
     * Takes an exception thrown by a dependency and returns an custom exception
     * that is more specific to the HTTP error
     */
    public static function getHttpErrorException(
        GuzzleException|ApiException|SabreClientHttpException|SabreClientException $e
    ): BadRequestException|NotFoundException|ForbiddenException|UnauthorizedException|HttpException {
        if ($e instanceof ApiException) {
            $rawResponseBody = $e->getResponseBody();
            if ($rawResponseBody instanceof \stdClass) {
                $message = $e->getMessage();
            } else {
                $responseBody = json_decode((string)$rawResponseBody, true);
                if ($responseBody === null) {
                    $message = $e->getMessage();
                } else {
                    $message = "";
                    if (is_array($responseBody)) {
                        if (isset($responseBody['error']['code'])) {
                            $message = $responseBody['error']['code'] . " - ";
                        }
                        if (isset($responseBody['error']['message'])) {
                            $message .= $responseBody['error']['message'];
                        }
                    } else {
                        // The response body contained valid JSON that decoded to just a string,
                        // or just an int or boolean value. There should not be any responses
                        // that are encoded like that.
                        // Report the message that is already in the exception.
                        $message = $e->getMessage();
                    }
                }
            }
        } else {
            $message = $e->getMessage();
        }

        switch ($e->getCode()) {
            case 400:
                return new BadRequestException(
                    $message,
                    $e->getCode(),
                    $e
                );
            case 401:
                return new UnauthorizedException(
                    $message,
                    $e->getCode(),
                    $e
                );
            case 403:
                return new ForbiddenException(
                    $message,
                    $e->getCode(),
                    $e
                );
            case 404:
                return new NotFoundException(
                    $message,
                    $e->getCode(),
                    $e
                );
            default:
                return new HttpException(
                    $message,
                    $e->getCode(),
                    $e
                );
        }
    }
}
