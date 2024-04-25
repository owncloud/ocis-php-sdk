<?php

namespace unit\Owncloud\OcisPhpSdk\Exception;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use OpenAPI\Client\ApiException;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sabre\HTTP\ClientException;
use Sabre\HTTP\ClientHttpException;

class ExceptionHelperTest extends TestCase
{
    /**
     * @return array<int,array{0:string,1:string,2:int, 3:class-string}>
     */
    public static function exceptionData(): array
    {
        return [
            ["GuzzleHttpRequestException", "bad request", 400, BadRequestException::class],
            ["GuzzleHttpRequestException", "unauthorized request", 401, UnauthorizedException::class],
            ["GuzzleHttpRequestException", "status 402 is unusual", 402, HttpException::class],
            ["GuzzleHttpRequestException", "request was forbidden", 403, ForbiddenException::class],
            ["GuzzleHttpRequestException", "not found", 404, NotFoundException::class],
            ["GuzzleHttpRequestException", "internal server error", 500, InternalServerErrorException::class],
            ["SabreClientHttpException", "Bad Request", 400, BadRequestException::class],
            ["SabreClientHttpException", "Unauthorized", 401, UnauthorizedException::class],
            ["SabreClientHttpException", "Payment Required", 402, HttpException::class],
            ["SabreClientHttpException", "Forbidden", 403, ForbiddenException::class],
            ["SabreClientHttpException", "Not Found", 404, NotFoundException::class],
            ["SabreClientHttpException", "Internal Server Error", 500, InternalServerErrorException::class],
            ["SabreClientException", "Bad Request", 400, BadRequestException::class],
            ["SabreClientException", "Unauthorized", 401, UnauthorizedException::class],
            ["SabreClientException", "Payment Required", 402, HttpException::class],
            ["SabreClientException", "Forbidden", 403, ForbiddenException::class],
            ["SabreClientException", "Not Found", 404, NotFoundException::class],
            ["SabreClientException", "Internal Server Error", 500, InternalServerErrorException::class],
            ["ApiException", "Bad Request", 400, BadRequestException::class],
            ["ApiException", "Unauthorized", 401, UnauthorizedException::class],
            ["ApiException", "Payment Required", 402, HttpException::class],
            ["ApiException", "Forbidden", 403, ForbiddenException::class],
            ["ApiException", "Not Found", 404, NotFoundException::class],
            ["ApiException", "Internal Server Error", 500, InternalServerErrorException::class],
        ];
    }

    /**
     * @param class-string $expectedExceptionClass
     * @dataProvider exceptionData
     */
    public function testGetHttpErrorException(
        string $originalExceptionToUse,
        string $exceptionMessage,
        int    $exceptionStatusCode,
        string $expectedExceptionClass
    ): void {
        $expectedExceptionMessage = $exceptionMessage;
        if ($originalExceptionToUse === "GuzzleHttpRequestException") {
            $request = $this->createMock(RequestInterface::class);
            assert($request instanceof RequestInterface);
            $response = new Response($exceptionStatusCode);
            $originalException = new RequestException($exceptionMessage, $request, $response);
        } elseif ($originalExceptionToUse === "SabreClientHttpException") {
            $response = new \Sabre\HTTP\Response($exceptionStatusCode);
            $originalException = new ClientHttpException($response);
        } elseif ($originalExceptionToUse === "SabreClientException") {
            $originalException = new ClientException($exceptionMessage, $exceptionStatusCode);
        } else { /* ApiException */
            $responseBodyArray = [];
            $responseBodyArray['error']['code'] = $exceptionStatusCode;
            $responseBodyArray['error']['message'] = $exceptionMessage;
            $responseBody = json_encode($responseBodyArray);
            if ($responseBody === false) {
                $this->fail("could not JSON encode the response body");
            }
            $originalException = new ApiException('some message', $exceptionStatusCode, [], $responseBody);
            $expectedExceptionMessage = "$exceptionStatusCode - $exceptionMessage";
        }
        $newException = ExceptionHelper::getHttpErrorException($originalException);
        $this->assertInstanceOf($expectedExceptionClass, $newException);
        $this->assertSame($expectedExceptionMessage, $newException->getMessage());
        $this->assertSame($exceptionStatusCode, $newException->getCode());
    }
}
