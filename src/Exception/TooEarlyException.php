<?php

namespace Owncloud\OcisPhpSdk\Exception;

/**
 * Exception for HTTP 425 errors
 */
class TooEarlyException extends \Exception
{
    public function __construct(int $code = 0, \Throwable $previous = null)
    {
        // set default message, otherwise it will be 'Unknown' because guzzle/http does not know the 425 code
        parent::__construct(
            'Too early',
            $code,
            $previous
        );
    }
}
