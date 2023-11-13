<?php

namespace integration\Owncloud\OcisPhpSdk;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';

use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;
use Owncloud\OcisPhpSdk\Ocis;

class UsersTest extends OcisPhpSdkTestCase
{
    /**
     * init a user
     * ocis is only aware of users after the first login, because we are using keycloak
     */
    private function initUser(string $name, string $password): void
    {
        $token = $this->getAccessToken($name, $password);
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $ocis->listMyDrives();
    }

    public function testGetAllUsers(): void
    {
        $this->initUser('einstein', 'relativity');
        $this->initUser('marie', 'radioactivity');
        $token = $this->getAccessToken('admin', 'admin');

        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers();
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertGreaterThanOrEqual(3, $users);
    }

    public function testSearchUsers(): void
    {
        $this->initUser('marie', 'radioactivity');
        $this->initUser('einstein', 'relativity');
        $this->initUser('moss', 'vista');
        $this->initUser('katherine', 'gemini');
        $token = $this->getAccessToken('admin', 'admin');
        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers('Albert');
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertCount(1, $users);
        $this->assertSame('Albert Einstein', $users[0]->getDisplayName());
    }

    public function testGetAllUsersAsUnprivilegedUser(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->initUser('marie', 'radioactivity');
        $token = $this->getAccessToken('einstein', 'relativity');

        $ocis = new Ocis($this->ocisUrl, $token, ['verify' => false]);
        $users = $ocis->getUsers();
        $this->assertContainsOnly('Owncloud\OcisPhpSdk\User', $users);
        $this->assertGreaterThanOrEqual(3, $users);
    }
}
