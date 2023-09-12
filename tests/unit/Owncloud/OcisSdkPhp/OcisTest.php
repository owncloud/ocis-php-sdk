<?php

namespace unit\Owncloud\OcisSdkPhp;

use Owncloud\OcisSdkPhp\Ocis;
use PHPUnit\Framework\TestCase;

class OcisTest extends TestCase {

    public function testCreateGuzzleConfigDefaultValues() {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token']
            ],
            $ocis->createGuzzleConfig()
        );
    }

    public function testCreateGuzzleConfigVerifyFalse() {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => ['Authorization' => 'Bearer token'],
                'verify' => false
            ],
            $ocis->createGuzzleConfig(['verify' => false])
        );
    }

    public function testCreateGuzzleConfigExtraHeader() {
        $ocis = new Ocis('http://something', 'token');
        $this->assertEquals(
            [
                'headers' => [
                    'Authorization' => 'Bearer token',
                    'X-something' => 'X-Data'
                ]
            ],
            $ocis->createGuzzleConfig(['headers' => ['X-something' => 'X-Data']])
        );
    }
}
