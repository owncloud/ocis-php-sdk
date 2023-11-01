<?php

namespace integration\Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;

class OcisTest extends TestCase
{
    private const CLIENT_ID = 'xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69';
    private const CLIENT_SECRET = 'UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh';
    private const UUID_REGEX_PATTERN = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}\$[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
//     Todo make configurable env
    private string $ocisUrl;
    private ?string $tokenUrl = null;
    private ?Client $guzzleClient = null;
    /**
     * @var array <string>
     */
    private $createdDrives = [];

    private function setOcisUrl()
    {
        $this->ocisUrl = getenv('OCIS_URL') ?? 'https://ocis.owncloud.test';
    }

    public function setUp(): void
    {
        $this->setOcisUrl();
        $guzzleClient = $this->getGuzzleClient();
        $response = $guzzleClient->get('.well-known/openid-configuration');
        $openIdConfigurationRaw = $response->getBody()->getContents();
        $openIdConfiguration = json_decode($openIdConfigurationRaw, true);
        if ($openIdConfiguration === null) {
            throw new \Exception('Could not decode openid configuration');
        }
        // @phpstan-ignore-next-line
        $this->tokenUrl = $openIdConfiguration['token_endpoint'];
    }

    public function tearDown(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        foreach ($this->createdDrives as $driveId) {
            $drive = $ocis->getDriveById($driveId);
            $drive->disable();
            $drive->delete();
        }
    }

    private function getGuzzleClient(): Client
    {
        if ($this->guzzleClient !== null) {
            return $this->guzzleClient;
        }
        $guzzleClient = new Client([
            'base_uri' => $this->ocisUrl,
            'verify' => false
        ]);
        $this->guzzleClient = $guzzleClient;
        return $this->guzzleClient;
    }

    private function getAccessToken(string $username, string $password): string
    {
        $guzzleClient = $this->getGuzzleClient();
        $response = $guzzleClient->post((string)$this->tokenUrl, [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'username' => $username,
                'password' => $password,
                'scope' => 'openid profile email offline_access'
            ]
        ]);
        $accessTokenResponse = json_decode($response->getBody()->getContents(), true);
        if ($accessTokenResponse === null) {
            throw new \Exception('Could not decode token response');
        }
        // @phpstan-ignore-next-line
        return $accessTokenResponse['access_token'];
    }

    public function testServiceUrlTrailingSlash(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl . '///', $token, ['verify' => false]);
        $drives = $ocis->listMyDrives();
        $this->assertTrue((is_array($drives) && count($drives) > 1));
    }

    public function testCreateDrive(): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $countDrivesAtStart = count(
            $ocis->listMyDrives()
        );
        $drive = $ocis->createDrive('first test drive');
        $this->createdDrives[] = $drive->getId();
        $this->assertMatchesRegularExpression(self::UUID_REGEX_PATTERN, $drive->getId());
        // there should be one more drive
        $this->assertCount($countDrivesAtStart + 1, $ocis->listMyDrives());
    }

    public function testCreateDriveNoPermissions(): void
    {
        $token = $this->getAccessToken('einstein', 'relativity');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->expectException(ForbiddenException::class);
        $countDrivesAtStart = count($ocis->listMyDrives());
        $ocis->createDrive('first test drive');
        // no new drive should have been created
        $this->assertCount($countDrivesAtStart, $ocis->listMyDrives());
    }

    /**
     * @return array<int,array<int,int>>
     */
    public function invalidQuotaProvider()
    {
        return [
            [-1],
            [-100],
        ];
    }
    /**
     * @dataProvider invalidQuotaProvider
     */
    public function testCreateDriveInvalidQuota(int $quota): void
    {
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $this->expectException(\InvalidArgumentException::class);
        $countDrivesAtStart = count($ocis->listMyDrives());
        $ocis->createDrive('drive with quota', $quota);
        // no new drive should have been created
        $this->assertCount($countDrivesAtStart, $ocis->listMyDrives());
    }
}
