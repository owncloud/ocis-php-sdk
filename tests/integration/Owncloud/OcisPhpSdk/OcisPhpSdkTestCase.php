<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use GuzzleHttp\Client;
use Owncloud\OcisPhpSdk\Ocis;
use PHPUnit\Framework\TestCase;

class OcisPhpSdkTestCase extends TestCase
{
    private const CLIENT_ID = 'xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69';
    private const CLIENT_SECRET = 'UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh';
    protected const UUID_REGEX_PATTERN = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}\$[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
    protected string $ocisUrl;
    private ?string $tokenUrl = null;
    private ?Client $guzzleClient = null;
    /**
     * @var array <string>
     */
    protected $createdDrives = [];

    /**
     * @var array <\Owncloud\OcisPhpSdk\Group>
     */
    protected $createdGroups = [];

    public function setUp(): void
    {
        $this->ocisUrl = getenv('OCIS_URL') ?: 'https://ocis.owncloud.test';
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
        foreach($this->createdGroups as $group) {
            $group->delete();
        }
    }

    protected function getGuzzleClient(): Client
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

    protected function getAccessToken(string $username, string $password): string
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
}
