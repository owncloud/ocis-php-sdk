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
     * Takes an exception thrown by a dependency and returns a custom exception
     * that is more specific to the HTTP error
     */
    public static function getHttpErrorException(
        GuzzleException|ApiException|SabreClientHttpException|SabreClientException $e
    ): BadRequestException|NotFoundException|ForbiddenException|UnauthorizedException|HttpException {
        $message = "";
        if ($e instanceof ApiException) {
            $rawResponseBody = $e->getResponseBody();
            if (is_string($rawResponseBody)) {
                $responseBody = json_decode($rawResponseBody, true);
                if (is_array($responseBody)) {
                    if (isset($responseBody['error']['code'])) {
                        $message = $responseBody['error']['code'] . " - ";
                    }
                    if (isset($responseBody['error']['message'])) {
                        $message .= $responseBody['error']['message'];
                    }
                }
            }
        }

        // still no message set, so use the message of the original exception
        if ($message === "") {
            $message = $e->getMessage();
        }

        return match ($e->getCode()) {
            400 => new BadRequestException(
                $message,
                $e->getCode(),
                $e
            ),
            401 => new UnauthorizedException(
                $message,
                $e->getCode(),
                $e
            ),
            403 => new ForbiddenException(
                $message,
                $e->getCode(),
                $e
            ),
            404 => new NotFoundException(
                $message,
                $e->getCode(),
                $e
            ),
            default => new HttpException(
                $message,
                $e->getCode(),
                $e
            ),
        };
    }
}
