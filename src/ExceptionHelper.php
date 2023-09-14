<?php

namespace Owncloud\OcisSdkPhp;

use GuzzleHttp\Exception\GuzzleException;
use OpenAPI\Client\ApiException;

class ExceptionHelper
{
    public static function getHttpErrorException(GuzzleException|ApiException $e): BadRequestException|NotFoundException|ForbiddenException|UnauthorizedException|\Exception
    {
        if ($e instanceof ApiException) {
            $responseBody = json_decode($e->getResponseBody(), true);
            if ($responseBody === null) {
                $message = $e->getMessage();
            } else {
                $message = "";
                if (isset($responseBody['error']['code'])) {
                    $message = $responseBody['error']['code'] . " - ";
                }
                if (isset($responseBody['error']['message'])) {
                    $message .= $responseBody['error']['message'];
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
                return new \Exception(
                    $message,
                    $e->getCode(),
                    $e
                );
        }
    }
}
