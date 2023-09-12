<?php

namespace Owncloud\OcisSdkPhp;

use OpenAPI\Client\ApiException;

class ForbiddenException extends ApiException
{
    public function __construct(ApiException $e)
    {
        $responseBody = json_decode($e->getResponseBody(), true);
        $message = "";
        if (isset($responseBody['error']['code'])) {
            $message = $responseBody['error']['code'] . " - ";
        }
        if (isset($responseBody['error']['message'])) {
            $message .= $responseBody['error']['message'];
        }
        parent::__construct(
            $message,
            $e->getCode(),
            $e->getResponseHeaders(),
            $e->getResponseBody()
        );
    }
}
