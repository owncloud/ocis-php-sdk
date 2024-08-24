<?php

namespace unit\Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class OcisWebfingerTest extends TestCase
{
    private function getValidToken(): string
    {
        $tokenHeader = [
            "alg" => "PS256",
            "kid" => "private-key",
            "typ" => "JWT",
        ];
        $tokenPayload = [
            "iss" => "https://sso.example.com",
            // this will generate a token that will contain `-` and `_` after base64Url encoding
            "special" => "????>>>????>>>",
        ];

        $base64EncodedToken = base64_encode((string)json_encode($tokenHeader)) . "." .
            base64_encode((string)json_encode($tokenPayload, JSON_UNESCAPED_UNICODE)) .
            ".signatureDoesNotMatter";
        // the token needs to be base64Url encoded not just base64
        // see https://jwt.io/introduction/
        return rtrim(\strtr($base64EncodedToken, '+/', '-_'), '=');
    }
    private function getGuzzleMock(?string $responseContent = null): MockObject
    {
        if ($responseContent === null) {
            $responseContent = '
                {
                    "subject": "acct:einstein@drive.ocis.test",
                    "links": [
                        {
                            "rel": "http://openid.net/specs/connect/1.0/issuer",
                            "href": "https://sso.example.org/cas/oidc/"
                        },
                        {
                            "rel": "http://webfinger.owncloud/rel/server-instance",
                            "href": "https://abc.drive.example.com",
                            "titles": {
                                "en": "oCIS Instance"
                            }
                        }
                    ]
                }';
        }

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($responseContent);
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getBody')->willReturn($streamMock);
        $guzzleMock = $this->createMock(Client::class);
        $guzzleMock->method('get')
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            ->with('https://webfinger.example.com?resource=acct:me@sso.example.com')
            ->willReturn($responseMock);
        return $guzzleMock;
    }
    public function testWebfinger(): void
    {
        $ocis = new Ocis(
            'https://webfinger.example.com',
            $this->getValidToken(),
            /* @phpstan-ignore-next-line because receiving a MockObject */
            [
                'webfinger' => true,
                'guzzle' => $this->getGuzzleMock(),
            ],
        );
        $this->assertSame("https://abc.drive.example.com", $ocis->getServiceUrl());
    }

    /**
     * @return array<array<string>>
     **/
    public static function invalidTokenProvider(): array
    {
        return [
            ["onlyHeaderNoPayload", "No payload found."],
            ["header.butPäylöädNötBäs€64", "Payload not Base64Url encoded."],

            // payload is not JSON
            ["header.cGF5bG9hZElzTm90SlNPTgo=", "Payload not valid JSON."],

            // payload is not an JSON object
            ["header.InBheWxvYWRJc05vdEFuT2JqZWN0Igo=", "Payload not valid JSON."],

            // payload is a JSON object, but does not contain 'iss' key
            ["header.eyJrZXkiOiAidmFsdWUifQo=", "Payload does not contain 'iss' key."],

            // payload contains 'iss' key but the value is null
            ["header.eyJpc3MiOiBudWxsfQo=", "'iss' key is not a string."],

            // payload contains 'iss' key but the value is 'https://'
            ["header.eyJpc3MiOiAiaHR0cHM6Ly8ifQo=", "Content of 'iss' has no 'host' part."],

            // payload contains 'iss' key but the value is 'host'
            ["header.eyJpc3MiOiAiaG9zdCJ9Cg==", "Content of 'iss' has no 'host' part."],

            // payload contains 'iss' key but the value is 'https://:9000'
            ["header.eyJpc3MiOiAiaHR0cHM6Ly86OTAwMCJ9Cg==", "Content of 'iss' has no 'host' part."],
        ];
    }
    /**
     * @dataProvider invalidTokenProvider
     */
    public function testWebfingerInvalidToken(string $token, string $expectedExceptionMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Could not decode token. ' . $expectedExceptionMessage,
        );
        /** @phan-suppress-next-line PhanNoopNew we expect an exception, so do not assign the result */
        new Ocis(
            'https://webfinger.example.com',
            $token,
            /* @phpstan-ignore-next-line because receiving a MockObject */
            [
                'webfinger' => true,
                'guzzle' => $this->getGuzzleMock(),
            ],
        );
    }

    /**
     * @return array<array<string>>
     **/
    public static function invalidResponseContentProvider(): array
    {
        return [
            ["notJson"],

            // no links
            ['{"subject": "acct:einstein@drive.ocis.test"}'],

            // links is not an array
            ['{"links": "string"}'],

            // links is an empty array
            ['{"links": []}'],

            //links don't have the correct rel
            ['{"links": [{"rel": "http://openid.net/specs/connect/1.0/issuer"},{"rel": "http://unrelated"}]}'],

            //correct rel, but no href
            ['{"links": [{"rel": "http://webfinger.owncloud/rel/server-instance"}]}'],
        ];
    }

    /**
     * @dataProvider invalidResponseContentProvider
     */
    public function testWebfingerInvalidResponse(string $responseContent): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage("invalid webfinger response");
        /** @phan-suppress-next-line PhanNoopNew we expect an exception, so do not assign the result */
        new Ocis(
            'https://webfinger.example.com',
            $this->getValidToken(),
            /* @phpstan-ignore-next-line because receiving a MockObject */
            [
                'webfinger' => true,
                'guzzle' => $this->getGuzzleMock($responseContent),
            ],
        );
    }
}
