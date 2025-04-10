<?php

namespace integration\Owncloud\OcisPhpSdk;

use GuzzleHttp\Client;
use Owncloud\OcisPhpSdk\Drive;
use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\TooEarlyException;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
use Owncloud\OcisPhpSdk\ShareReceived;
use Owncloud\OcisPhpSdk\SharingRole;
use PHPUnit\Framework\TestCase;

class OcisPhpSdkTestCase extends TestCase
{
    private const CLIENT_ID = 'xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69';
    private const CLIENT_SECRET = 'UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh';
    protected const VALID_LINK_PASSWORD = "p@$\$w0rD";
    protected string $ocisUrl;
    private ?string $tokenUrl = null;
    private ?Client $guzzleClient = null;
    /**
     * @var array<string>
     */
    protected array $createdDrives = [];
    /**
     * list of files and folders that were created during the tests
     * currently only for the personal drive
     * @var array<string, array<string>> driveId[] => resourcePath
     */
    protected array $createdResources = [];

    /**
     * @var array<\Owncloud\OcisPhpSdk\Group>
     */
    protected array $createdGroups = [];

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
        $ocis = $this->getOcis('admin', 'admin');
        foreach ($this->createdDrives as $driveId) {
            try {
                $drive = $ocis->getDriveById($driveId);
                $drive->disable();
                $drive->delete();
            } catch (NotFoundException) {
                // ignore, we don't care if the drive was already deleted
            }

        }
        foreach ($this->createdGroups as $group) {
            $group->delete();
        }
        $this->createdGroups = [];

        foreach ($this->createdResources as $driveId => $resources) {
            $drive = $ocis->getDriveById($driveId);
            foreach ($resources as $resource) {
                try {
                    $drive->deleteResource($resource);
                } catch (NotFoundException) {
                    // ignore, we don't care if the resource was already deleted
                }
            }
        }
    }

    protected function getGuzzleClient(): Client
    {
        if ($this->guzzleClient !== null) {
            return $this->guzzleClient;
        }
        $guzzleClient = new Client([
            'base_uri' => $this->ocisUrl,
            'verify' => false,
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
                'scope' => 'openid profile email offline_access',
            ],
        ]);
        $accessTokenResponse = json_decode($response->getBody()->getContents(), true);
        if ($accessTokenResponse === null) {
            throw new \Exception('Could not decode token response');
        }
        // @phpstan-ignore-next-line
        return $accessTokenResponse['access_token'];
    }

    protected function getOcis(string $username, string $password): Ocis
    {
        $token = $this->getAccessToken($username, $password);
        return new Ocis($this->ocisUrl, $token, ['verify' => false]);
    }

    protected function getUUIDv4Regex(): string
    {
        return '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';
    }

    protected function getFileIdRegex(): string
    {
        return $this->getUUIDv4Regex() . '\\$' . $this->getUUIDv4Regex() . '!' . $this->getUUIDv4Regex();
    }


    protected function getSpaceIdRegex(): string
    {
        return $this->getUUIDv4Regex() . '\\$' . $this->getUUIDv4Regex();
    }
    /**
     * init a user
     * ocis is only aware of users after the first login, because we are using keycloak
     */
    protected function initUser(string $name, string $password): Ocis
    {
        $ocis = $this->getOcis($name, $password);
        $ocis->getMyDrives();
        return $ocis;
    }

    protected function getRoleByName(OcisResource $resource, string $roleName): SharingRole
    {
        foreach ($resource->getRoles() as $role) {
            if ($role->getDisplayName() === $roleName) {
                return $role;
            }
        }
        throw new \Exception('Role not found');
    }

    protected function getContentOfResource425Save(OcisResource $resource): string
    {
        $content = null;
        $timeout = 10;
        $count = 0;
        while ($content === null) {
            try {
                $content = $resource->getContent();
            } catch (TooEarlyException) {
                $this->assertLessThan($timeout, $count);
                sleep(1);
                $count++;
            }
        }
        // check for null is done above
        // @phan-suppress-next-line PhanTypeMismatchReturnNullable
        return $content;
    }

    protected function getPersonalDrive(Ocis $ocis): Drive
    {
        return $ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL,
        )[0];
    }

    /**
     * wrapper around `getSharedWithMe()` that makes sure the share is auto-accepted
     * the auto-accepting happens async, so calling getSharedWithMe() directly might be too early.
     * @param Ocis $ocis
     * @return array<ShareReceived>
     */
    protected function getSharedWithMeWaitTillShareIsAccepted(Ocis $ocis): array
    {
        $receivedShares = [];
        $timeout = time() + 10;
        while (time() < $timeout) {
            $receivedShares = $ocis->getSharedWithMe();
            $allSharesAccepted = true;
            foreach ($receivedShares as $share) {
                if ($share->isClientSynchronized() === false) {
                    $allSharesAccepted = false;
                    sleep(1);
                    break;
                }
            }
            if ($allSharesAccepted === true) {
                break;
            }
        }
        return $receivedShares;
    }
    private static function getWrapperGuzzleClient(): Client
    {
        $ociswrapperUrl = getenv('OCISWRAPPER_URL') ?: 'http://ociswrapper.owncloud.test';
        return new Client(['base_uri' => $ociswrapperUrl]);
    }

    protected static function setOcisSetting(string $key, string $value): void
    {
        $response = self::getWrapperGuzzleClient()->request(
            'PUT',
            '/config',
            ['body' => '{"' . $key . '": "' . $value . '"}'],
        );
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to set OCIS setting');
        }
    }

    protected static function resetOcisSettings(): void
    {
        $response = self::getWrapperGuzzleClient()->request(
            'DELETE',
            '/rollback',
        );
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to reset OCIS settings');
        }
    }

    /**
     * Get the role id by name
     *
     * @param string $permissionsRole
     *
     * @return string
     *
     * @throws \Exception
     */
    protected static function getPermissionsRoleIdByName(
        string $permissionsRole,
    ): string {
        switch ($permissionsRole) {
            case 'Viewer':
                return 'b1e2218d-eef8-4d4c-b82d-0f1a1b48f3b5';
            case 'Space Viewer':
                return 'a8d5fe5e-96e3-418d-825b-534dbdf22b99';
            case 'Editor':
                return 'fb6c3e19-e378-47e5-b277-9732f9de6e21';
            case 'Space Editor':
                return '58c63c02-1d89-4572-916a-870abc5a1b7d';
            case 'File Editor':
                return '2d00ce52-1fc2-4dbc-8b95-a73b73395f5a';
            case 'Co Owner':
                return '3a4ba8e9-6a0d-4235-9140-0e7a34007abe';
            case 'Uploader':
                return '1c996275-f1c9-4e71-abdf-a42f6495e960';
            case 'Manager':
                return '312c0871-5ef7-4b3a-85b6-0e4074c64049';
            default:
                throw new \Exception('Role ' . $permissionsRole . ' not found');
        }
    }
}
