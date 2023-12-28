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
            "typ" => "JWT"
        ];
        $tokenPayload = [
            "iss" => "https://sso.example.com",
        ];
        return base64_encode((string)json_encode($tokenHeader)) . "." .
            base64_encode((string)json_encode($tokenPayload)) .
            ".signatureDoesNotMatter";
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
                'guzzle' => $this->getGuzzleMock()
            ]
        );
        $this->assertSame("https://abc.drive.example.com", $ocis->getServiceUrl());
    }

    /**
     * @return array<array<string>>
     **/
    public static function invalidTokenProvider(): array
    {
        return [
            ["onlyHeaderNoPayload"],
            ["header.butPäylöädNötBäs€64"],

            // payload is not JSON
            ["header.cGF5bG9hZElzTm90SlNPTgo="],

            // payload is not an JSON object
            ["header.InBheWxvYWRJc05vdEFuT2JqZWN0Igo="],

            // payload is a JSON object, but does not contain 'iss' key
            ["header.eyJrZXkiOiAidmFsdWUifQo="],

            // payload contains 'iss' key but the value is null
            ["header.eyJpc3MiOiBudWxsfQo="],

            // payload contains 'iss' key but the value is 'https://'
            ["header.eyJpc3MiOiAiaHR0cHM6Ly8ifQo="],

            // payload contains 'iss' key but the value is 'host'
            ["header.eyJpc3MiOiAiaG9zdCJ9Cg=="],

            // payload contains 'iss' key but the value is 'https://:9000'
            ["header.eyJpc3MiOiAiaHR0cHM6Ly86OTAwMCJ9Cg=="],
        ];
    }
    /**
     * @dataProvider invalidTokenProvider
     */
    public function testWebfingerInvalidToken(string $token): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("could not decode token");
        $ocis = new Ocis(
            'https://webfinger.example.com',
            $token,
            /* @phpstan-ignore-next-line because receiving a MockObject */
            [
                'webfinger' => true,
                'guzzle' => $this->getGuzzleMock()
            ]
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

            // links is not array
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
        $ocis = new Ocis(
            'https://webfinger.example.com',
            $this->getValidToken(),
            /* @phpstan-ignore-next-line because receiving a MockObject */
            [
                'webfinger' => true,
                'guzzle' => $this->getGuzzleMock($responseContent)
            ]
        );
    }
}
