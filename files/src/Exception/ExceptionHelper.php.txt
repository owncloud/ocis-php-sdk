<?php

namespace Owncloud\OcisPhpSdk\Exception;

use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\OdataError;
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
        GuzzleException|ApiException|SabreClientHttpException|SabreClientException|HttpException $e,
    ): BadRequestException|
    NotFoundException|
    ForbiddenException|
    UnauthorizedException|
    HttpException|
    ConflictException|
    TooEarlyException|
    InternalServerErrorException {
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
                $e,
            ),
            401 => new UnauthorizedException(
                $message,
                $e->getCode(),
                $e,
            ),
            403 => new ForbiddenException(
                $message,
                $e->getCode(),
                $e,
            ),
            404 => new NotFoundException(
                $message,
                $e->getCode(),
                $e,
            ),
            409 => new ConflictException(
                $message,
                $e->getCode(),
                $e,
            ),
            425 => new TooEarlyException(
                $e->getCode(),
                $e,
            ),
            500 => new InternalServerErrorException(
                $message,
                $e->getCode(),
                $e,
            ),
            default => new HttpException(
                $message,
                $e->getCode(),
                $e,
            ),
        };
    }

    /**
     * Takes an OdataError object and returns a custom exception
     * that is more specific to the error if it has a numeric error code.
     * Otherwise, it returns an InvalidResponseException
     *
     * @param string $methodName the name of the method that received the OdataError
     */
    public static function getExceptionFromOdataError(
        OdataError $odataError,
        string $methodName,
    ): BadRequestException|
    NotFoundException|
    ForbiddenException|
    UnauthorizedException|
    HttpException|
    ConflictException|
    TooEarlyException|
    InternalServerErrorException {
        $error = $odataError->getError();
        $errorCode = $error->getCode();
        $errorMessage = $error->getMessage();
        if (is_numeric($errorCode)) {
            $genericHttpException = new HttpException(
                $errorMessage,
                (int) $errorCode,
            );
            return self::getHttpErrorException($genericHttpException);
        }
        // The error code was not numeric, which is not really expected to happen.
        // So throw an InternalServerErrorException with numeric code 500 and
        // put the details in the exception message so that the caller has a chance
        // to understand what happened.
        return new InternalServerErrorException(
            "$methodName returned an OdataError with code '" .
            $errorCode .
            "' and message '" . $errorMessage . "'",
            500,
        );
    }
}
